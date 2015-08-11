package main

import (
	"flag"
        "os/exec"
	"net/http"
	"sync"
        "strings" 

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/log"
)

const (
	namespace = "cron" // For Prometheus metrics.
)

var (
	listenAddress = flag.String("telemetry.address", ":9114", "Address on which to expose metrics.")
	metricsPath  = flag.String("telemetry.endpoint", "/metrics", "Path under which to expose metrics.")
	cronUsers   = flag.String("cron.users", "ubuntu,user2,user3", "list of cron users")
        syslogfile = flag.String("syslog.file", "/var/log/syslog", "Syslog file location")
)

// Exporter collects cron stats from machine of a specified user and exports them using
// the prometheus metrics package.
type Exporter struct {
        CronUser string
	mutex  sync.RWMutex
	totalCronJobs    prometheus.Gauge
        cronRunningStatus  *prometheus.GaugeVec
}

// NewCronExporter returns an initialized Exporter.
func NewCronExporter(cronuser string) *Exporter {
	return &Exporter{
		CronUser: cronuser,
                totalCronJobs: prometheus.NewGauge(prometheus.GaugeOpts{
                        Namespace: namespace,
                        Name:      "total_crons",
                        Help:      "Total no of crons",
                }),
                cronRunningStatus: prometheus.NewGaugeVec(prometheus.GaugeOpts{
                        Namespace: namespace,
                        Name:      "status",
                        Help:      "cronjob last running status",
                },      
                        []string{"user", "pattern", "readable", "command" ,"nextrun","previousrun"},
                ),
	}
}

// Describe describes all the metrics ever exported by the cron exporter. It
// implements prometheus.Collector.
func (e *Exporter) Describe(ch chan<- *prometheus.Desc) {
	e.totalCronJobs.Describe(ch)
        e.cronRunningStatus.Describe(ch)
}

func getCronScheduleFromPHP(cronString string) string {
    phpcmd := "php ./CronSchedule.php "+"'"+cronString+"'"
    out, _ := exec.Command("bash", "-c", phpcmd).Output()
    return string(out)
}


func grepCronLogStatus(cron_command string, cron_last_run string) string{
   grepcmd := "grep -r CRON "+*syslogfile+ " | grep '"+cron_last_run+"'"+ " | grep '"+strings.TrimSpace(cron_command)+"'" 
   out, _ := exec.Command("bash", "-c", grepcmd).Output()
   if string(out) != "" {
      return "success"
   }else{
      return "failed"
   }
}

func (e *Exporter) scrape(ch chan<- prometheus.Metric) error {
    total_cron := 0
    cron_users_list := strings.Split(*cronUsers, ",")
    for _, user := range cron_users_list{
        user = strings.TrimSpace(user)
        cmd := "sudo /usr/bin/crontab -l -u " + user + " | grep -v '^#' | sed 's/^ *//;/^[*@0-9]/!d'"
        output, _ := exec.Command("bash", "-c", cmd).Output()
        if string(output) != ""{
          crons := string(output)
          crons = strings.TrimSpace(crons)
          crons = strings.Replace(crons,"\n","|",-1)
          cronSlice := strings.Split(crons, "|")
          for _, cron := range cronSlice{
              if cron != ""{
                   var cron_pattern string
                   var cron_command string
                   matches := strings.Fields(cron) 
                   for _,val := range matches[:5]{
                       cron_pattern += string(val)+" "
                   }
                   for _, val1 := range matches[5:]{
                       cron_command += string(val1)+" "
                   }
                   cron_pattern = strings.TrimSpace(cron_pattern)
                   cron_command = strings.TrimSpace(cron_command)

                   cronruns := getCronScheduleFromPHP(cron_pattern)
                   cronrunsSlice := strings.Split(cronruns, "|")
                   humanReadable := cronrunsSlice[len(cronrunsSlice)-3]
                   cronNextRunTime := cronrunsSlice[len(cronrunsSlice)-2]
                   cronLastRunTime := cronrunsSlice[len(cronrunsSlice)-1]

                   var cronLastRun string
                   cronLastRunSlice := strings.Split(cronLastRunTime, " ")
                   cronLastRun = cronLastRunSlice[len(cronLastRunSlice)-3]+"  "+cronLastRunSlice[len(cronLastRunSlice)-2]+" "+cronLastRunSlice[len(cronLastRunSlice)-1]
 
                   cron_status := grepCronLogStatus(cron_command, cronLastRun)
                   if cron_status == "success"{
                        e.cronRunningStatus.WithLabelValues(user, cron_pattern,humanReadable,cron_command,cronNextRunTime,cronLastRunTime).Set(float64(1))
                   }
                   if cron_status == "failed"{
                        e.cronRunningStatus.WithLabelValues(user, cron_pattern,humanReadable,cron_command,cronNextRunTime,cronLastRunTime).Set(float64(0))
                   }
               }
           }
       total_cron = total_cron+ len(cronSlice)
    }
  }
    e.totalCronJobs.Set(float64(total_cron))
    return nil
}

// Collect fetches the stats of a user and delivers them
// as Prometheus metrics. It implements prometheus.Collector.
func (e *Exporter) Collect(ch chan<- prometheus.Metric) {
	e.mutex.Lock() // To protect metrics from concurrent collects.
	defer e.mutex.Unlock()
        if err := e.scrape(ch); err != nil {
		log.Printf("Error scraping cron: %s", err)
	}
	e.totalCronJobs.Collect(ch)
        e.cronRunningStatus.Collect(ch)
	return
}

func main() {
	flag.Parse()

	exporter := NewCronExporter(*cronUsers)
	prometheus.MustRegister(exporter)
	http.Handle(*metricsPath, prometheus.Handler())
        http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
	    w.Write([]byte(`<html>
                <head><title>Cronjob exporter</title></head>
                <body>
                   <h1>Cronjob exporter</h1>
                   <p><a href='` + *metricsPath + `'>Metrics</a></p>
                   </body>
                </html>
              `))
	})
	log.Infof("Starting Server: %s", *listenAddress)
	log.Fatal(http.ListenAndServe(*listenAddress, nil))
}
