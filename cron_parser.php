<?php


class CronSchedule
{
	// The actual minutes, hours, daysOfMonth, months, daysOfWeek and years selected by the provided cron specification.
	private    $_minutes            = array();
	private    $_hours                = array();
	private    $_daysOfMonth        = array();
	private    $_months            = array();
	private    $_daysOfWeek        = array();
	private    $_years                = array();

	// The original cron specification in compiled form.
	private $_cronMinutes        = array();
	private $_cronHours            = array();
	private $_cronDaysOfMonth    = array();
	private $_cronMonths        = array();
	private $_cronDaysOfWeek    = array();
	private $_cronYears            = array();

	// The language table
	private $_lang                = FALSE;


	/**
	 * Minimum and maximum years to cope with the Year 2038 problem in UNIX. We run PHP which most likely runs on a UNIX environment so we
	 * must assume vulnerability.
	 */
	protected $RANGE_YEARS_MIN    = 1970;    // Must match date range supported by date(). See also: http://en.wikipedia.org/wiki/Year_2038_problem
	protected $RANGE_YEARS_MAX    = 2037;    // Must match date range supported by date(). See also: http://en.wikipedia.org/wiki/Year_2038_problem


	/**
	 * Function:    __construct
	 *
	 * Description:    Performs only base initialization, including language initialization.
	 *
	 * Parameters:    $language            The languagecode of the chosen language.
	 */
	public function __construct($language = 'en')
	{
		$this->initLang($language);
	}

	//
	// Function:    fromCronString
	//
	// Description:    Creates a new Schedule object based on a Cron specification.
	//
	// Parameters:    $cronSpec            A string containing a cron specification.
	//                $language            The language to use to create a natural language representation of the string
	//
	// Result:        A new Schedule object. An \Exception is thrown if the specification is invalid.
	//

	final public static function fromCronString($cronSpec = '* * * * * *', $language = 'en')
	{

		// Split input liberal. Single or multiple Spaces, Tabs and Newlines are all allowed as separators.
		if(count($elements = preg_split('/\s+/', $cronSpec)) < 5)
			throw new Exception('Invalid specification.');

		/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		// Named ranges in cron entries
		/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		$arrMonths        = array('JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12);
		$arrDaysOfWeek    = array('SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6);


		// Translate the cron specification into arrays that hold specifications of the actual dates
		$newCron = new CronSchedule($language);
		$newCron->_cronMinutes        = $newCron->cronInterpret($elements[0],                               0,                           59, array(),            'minutes');
		$newCron->_cronHours        = $newCron->cronInterpret($elements[1],                               0,                           23, array(),            'hours');
		$newCron->_cronDaysOfMonth    = $newCron->cronInterpret($elements[2],                            1,                           31, array(),            'daysOfMonth');
		$newCron->_cronMonths        = $newCron->cronInterpret($elements[3],                            1,                           12, $arrMonths,        'months');
		$newCron->_cronDaysOfWeek    = $newCron->cronInterpret($elements[4],                            0,                            6, $arrDaysOfWeek,    'daysOfWeek');


		$newCron->_minutes            = $newCron->cronCreateItems($newCron->_cronMinutes);
		$newCron->_hours            = $newCron->cronCreateItems($newCron->_cronHours);
		$newCron->_daysOfMonth        = $newCron->cronCreateItems($newCron->_cronDaysOfMonth);
		$newCron->_months            = $newCron->cronCreateItems($newCron->_cronMonths);
		$newCron->_daysOfWeek        = $newCron->cronCreateItems($newCron->_cronDaysOfWeek);

		if (isset($elements[5])) {
			$newCron->_cronYears        = $newCron->cronInterpret($elements[5], $newCron->RANGE_YEARS_MIN, $newCron->RANGE_YEARS_MAX, array(),            'years');
			$newCron->_years            = $newCron->cronCreateItems($newCron->_cronYears);
		}

		return $newCron;
	}


	/*
	 * Function:    cronInterpret
	 *
	 * Description:    Interprets a single field from a cron specification. Throws an \Exception if the specification is in some way invalid.
	 *
	 * Parameters:    $specification        The actual text from the spefication, such as 12-38/3
	 *                $rangeMin            The lowest value for specification.
	 *                $rangeMax            The highest value for specification
	 *                $namesItems            A key/value pair where value is a value between $rangeMin and $rangeMax and key is the name for that value.
	 *                $errorName            The name of the category to use in case of an error.
	 *
	 * Result:        An array with entries, each of which is an array with the following fields:
	 *                'number1'            The first number of the range or the number specified
	 *                'number2'            The second number of the range if a range is specified
	 *                'hasInterval'        TRUE if a range is specified. FALSE otherwise
	 *                'interval'            The interval if a range is specified.
	 */
	final private function cronInterpret($specification, $rangeMin, $rangeMax, $namedItems, $errorName)
	{

		if((!is_string($specification)) && (!(is_int($specification))))
			throw new Exception('Invalid specification.');


		// Multiple values, separated by comma
		$specs = array();
		$specs['rangeMin'] = $rangeMin;
		$specs['rangeMax'] = $rangeMax;
		$specs['elements'] = array();
		$arrSegments = explode(',', $specification);
		foreach($arrSegments as $segment)
		{
			$hasRange        = (($posRange        = strpos($segment, '-')) !== FALSE);
			$hasInterval    = (($posIncrement    = strpos($segment, '/')) !== FALSE);

			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			// Check: Increment without range is invalid

			//if(!$hasRange && $hasInterval)                                                throw new \Exception("Invalid Range ($errorName).");


			// Check: Increment must be final specification

			if($hasRange && $hasInterval)
				if($posIncrement < $posRange)                                            throw new \Exception("Invalid order ($errorName).");


			// GetSegments

			$segmentNumber1        = $segment;
			$segmentNumber2        = '';
			$segmentIncrement    = '';
			$intIncrement        = 1;
			if($hasInterval)
			{
				$segmentNumber1 = substr($segment, 0, $posIncrement);
				$segmentIncrement = substr($segment, $posIncrement + 1);
			}

			if($hasRange)
			{
				$segmentNumber2 = substr($segmentNumber1, $posRange + 1);
				$segmentNumber1 = substr($segmentNumber1, 0, $posRange);
			}


			// Get and validate first value in range

			if($segmentNumber1 == '*')
			{
				$intNumber1 = $rangeMin;
				$intNumber2 = $rangeMax;
				$hasRange    = TRUE;
			}
			else
			{
				if(array_key_exists(strtoupper($segmentNumber1), $namedItems)) $segmentNumber1 = $namedItems[strtoupper($segmentNumber1)];
				if(((string) ($intNumber1 = (int) $segmentNumber1)) != $segmentNumber1)        throw new \Exception("Invalid symbol ($errorName).");
				if(($intNumber1 < $rangeMin) || ($intNumber1 > $rangeMax))                    throw new \Exception("Out of bounds ($errorName).");


				// Get and validate second value in range

				if($hasRange)
				{
					if(array_key_exists(strtoupper($segmentNumber2), $namedItems)) $segmentNumber2 = $namedItems[strtoupper($segmentNumber2)];
					if(((string) ($intNumber2 = (int) $segmentNumber2)) != $segmentNumber2)    throw new \Exception("Invalid symbol ($errorName).");
					if(($intNumber2 < $rangeMin) || ($intNumber2 > $rangeMax))                throw new \Exception("Out of bounds ($errorName).");
					if($intNumber1 > $intNumber2)                                            throw new \Exception("Invalid range ($errorName).");
				}
			}


			// Get and validate increment

			if($hasInterval)
			{
				if(($intIncrement = (int) $segmentIncrement) != $segmentIncrement)        throw new \Exception("Invalid symbol ($errorName).");
				if($intIncrement < 1)                                                    throw new \Exception("Out of bounds ($errorName).");
			}


			// Apply range and increment

			$elem = array();
			$elem['number1'] = $intNumber1;
			$elem['hasInterval'] = $hasRange;
			if($hasRange)
			{
				$elem['number2']    = $intNumber2;
				$elem['interval']    = $intIncrement;
			}
			$specs['elements'][] = $elem;
		}

		return $specs;
	}


	//
	// Function:    cronCreateItems
	//
	// Description:    Uses the interpreted cron specification of a single item from a cron specification to create an array with keys that match the
	//                selected items.
	//
	// Parameters:    $cronInterpreted    The interpreted specification
	//
	// Result:        An array where each key identifies a matching entry. E.g. the cron specification */10 for minutes will yield an array
	//                [0] => 1
	//                [10] => 1
	//                [20] => 1
	//                [30] => 1
	//                [40] => 1
	//                [50] => 1
	//

	final private function cronCreateItems($cronInterpreted)
	{
		$items = array();

		foreach($cronInterpreted['elements'] as $elem)
		{
			if(!$elem['hasInterval'])
				$items[$elem['number1']] = TRUE;
			else
				for($number = $elem['number1']; $number <= $elem['number2']; $number += $elem['interval'])
					$items[$number] = TRUE;
		}
		ksort($items);
		return $items;
	}


	//
	// Function:    dtFromParameters
	//
	// Description:    Transforms a flexible parameter passing of a datetime specification into an internally used array.
	//
	// Parameters:    $time                If a string interpreted as a datetime string in the YYYY-MM-DD HH:II format and other parameters ignored.
	//                                    If an array $minute, $hour, $day, $month and $year are passed as keys 0-4 and other parameters ignored.
	//                                    If a string, interpreted as unix time.
	//                                    If omitted or specified FALSE, defaults to the current time.
	//
	// Result:        An array with indices 0-4 holding the actual interpreted values for $minute, $hour, $day, $month and $year.
	//

	final private function dtFromParameters($time = FALSE)
	{
		if($time === FALSE)
		{
			$arrTime = getDate();
			return array($arrTime['minutes'], $arrTime['hours'], $arrTime['mday'], $arrTime['mon'], $arrTime['year']);
		}
		elseif(is_array($time))
			return $time;
		elseif(is_string($time))
		{
			$arrTime = getDate(strtotime($time));
			return array($arrTime['minutes'], $arrTime['hours'], $arrTime['mday'], $arrTime['mon'], $arrTime['year']);
		}elseif(is_int($time))
		{
			$arrTime = getDate($time);
			return array($arrTime['minutes'], $arrTime['hours'], $arrTime['mday'], $arrTime['mon'], $arrTime['year']);
		}
	}

	final private function dtAsString($arrDt)
	{
		if($arrDt === FALSE)
			return FALSE;
		return $arrDt[4].'-'.(strlen($arrDt[3]) == 1 ? '0' : '').$arrDt[3].'-'.(strlen($arrDt[2]) == 1 ? '0' : '').$arrDt[2].' '.(strlen($arrDt[1]) == 1 ? '0' : '').$arrDt[1].':'.(strlen($arrDt[0]) == 1 ? '0' : '').$arrDt[0].':00';
	}


	//
	// Function:    match
	//
	// Description:    Returns TRUE if the specified date and time corresponds to a scheduled point in time. FALSE otherwise.
	//
	// Parameters:    $time                If a string interpreted as a datetime string in the YYYY-MM-DD HH:II format and other parameters ignored.
	//                                    If an array $minute, $hour, $day, $month and $year are passed as keys 0-4 and other parameters ignored.
	//                                    If a string, interpreted as unix time.
	//                                    If omitted or specified FALSE, defaults to the current time.
	//
	// Result:        TRUE if the schedule matches the specified datetime. FALSE otherwise.
	//

	final public function match($time = FALSE)
	{

		// Convert parameters to array datetime

		$arrDT = $this->dtFromParameters($time);


		// Verify match


		// Years
		if(!array_key_exists($arrDT[4], $this->_years)) return FALSE;
		// Day of week
		if(!array_key_exists(date('w', strtotime($arrDT[4].'-'.$arrDT[3].'-'.$arrDT[2])), $this->_daysOfWeek)) return FALSE;
		// Month
		if(!array_key_exists($arrDT[3], $this->_months)) return FALSE;
		// Day of month
		if(!array_key_exists($arrDT[2], $this->_daysOfMonth)) return FALSE;
		// Hours
		if(!array_key_exists($arrDT[1], $this->_hours)) return FALSE;
		// Minutes
		if(!array_key_exists($arrDT[0], $this->_minutes)) return FALSE;

		return TRUE;
	}






	//
	// Function:
	//
	// Description:
	//
	// Parameters:
	//
	// Result:
	//

	final private function getClass($spec)
	{
		if(!$this->classIsSpecified($spec))        return '0';
		if($this->classIsSingleFixed($spec))    return '1';
		return '2';
	}


	//
	// Function:
	//
	// Description:    Returns TRUE if the Cron Specification is specified. FALSE otherwise. This is true if the specification has more than one entry
	//                or is anything than the entire approved range ("*").
	//
	// Parameters:
	//
	// Result:
	//

	final private function classIsSpecified($spec)
	{
		if($spec['elements'][0]['hasInterval'] == FALSE)            return TRUE;
		if($spec['elements'][0]['number1'] != $spec['rangeMin'])    return TRUE;
		if($spec['elements'][0]['number2'] != $spec['rangeMax'])    return TRUE;
		if($spec['elements'][0]['interval'] != 1)                    return TRUE;
		return FALSE;
	}


	//
	// Function:
	//
	// Description:    Returns TRUE if the Cron Specification is specified as a single value. FALSE otherwise. This is true only if there is only
	//                one entry and the entry is only a single number (e.g. "10")
	//
	// Parameters:
	//
	// Result:
	//

	final private function classIsSingleFixed($spec)
	{
		return (count($spec['elements']) == 1) && (!$spec['elements'][0]['hasInterval']);
	}

	final private function initLang($language = 'en')
	{
		switch($language)
		{
			case 'en':
				$this->_lang['elemMin: at_the_hour']                            = 'at the hour';
				$this->_lang['elemMin: after_the_hour_every_X_minute']            = 'every minute';
				$this->_lang['elemMin: after_the_hour_every_X_minute_plural']    = 'every @1 minutes';
				$this->_lang['elemMin: every_consecutive_minute']                = 'every consecutive minute';
				$this->_lang['elemMin: every_consecutive_minute_plural']        = 'every consecutive @1 minutes';
				$this->_lang['elemMin: every_minute']                            = 'every minute';
				$this->_lang['elemMin: between_X_and_Y']                        = 'from the @1 to the @2';
				$this->_lang['elemMin: at_X:Y']                                    = 'At @1:@2';
				$this->_lang['elemHour: past_X:00']                                = 'past @1:00';
				$this->_lang['elemHour: between_X:00_and_Y:59']                    = 'between @1:00 and @2:59';
				$this->_lang['elemHour: in_the_60_minutes_past_']                = 'in the 60 minutes past every consecutive hour';
				$this->_lang['elemHour: in_the_60_minutes_past__plural']        = 'in the 60 minutes past every consecutive @1 hours';
				$this->_lang['elemHour: past_every_consecutive_']                = 'past every consecutive hour';
				$this->_lang['elemHour: past_every_consecutive__plural']        = 'past every consecutive @1 hours';
				$this->_lang['elemHour: past_every_hour']                        = 'past every hour';
				$this->_lang['elemDOM: the_X']                                    = 'the @1';
				$this->_lang['elemDOM: every_consecutive_day']                    = 'every consecutive day';
				$this->_lang['elemDOM: every_consecutive_day_plural']            = 'every consecutive @1 days';
				$this->_lang['elemDOM: on_every_day']                            = 'on every day';
				$this->_lang['elemDOM: between_the_Xth_and_Yth']                = 'between the @1 and the @2';
				$this->_lang['elemDOM: on_the_X']                                = 'on the @1';
				$this->_lang['elemDOM: on_X']                                    = 'on @1';
				$this->_lang['elemMonth: every_X']                                = 'every @1';
				$this->_lang['elemMonth: every_consecutive_month']                = 'every consecutive month';
				$this->_lang['elemMonth: every_consecutive_month_plural']        = 'every consecutive @1 months';
				$this->_lang['elemMonth: between_X_and_Y']                        = 'from @1 to @2';
				$this->_lang['elemMonth: of_every_month']                        = 'of every month';
				$this->_lang['elemMonth: during_every_X']                        = 'during every @1';
				$this->_lang['elemMonth: during_X']                                = 'during @1';
				$this->_lang['elemYear: in_X']                                    = 'in @1';
				$this->_lang['elemYear: every_consecutive_year']                = 'every consecutive year';
				$this->_lang['elemYear: every_consecutive_year_plural']            = 'every consecutive @1 years';
				$this->_lang['elemYear: from_X_through_Y']                        = 'from @1 through @2';
				$this->_lang['elemDOW: on_every_day']                            = 'on every day';
				$this->_lang['elemDOW: on_X']                                    = 'on @1';
				$this->_lang['elemDOW: but_only_on_X']                            = 'but only if the event takes place on @1';
				$this->_lang['separator_and']                                    = 'and';
				$this->_lang['separator_or']                                    = 'or';
				$this->_lang['day: 0_plural']                                    = 'Sundays';
				$this->_lang['day: 1_plural']                                    = 'Mondays';
				$this->_lang['day: 2_plural']                                    = 'Tuesdays';
				$this->_lang['day: 3_plural']                                    = 'Wednesdays';
				$this->_lang['day: 4_plural']                                    = 'Thursdays';
				$this->_lang['day: 5_plural']                                    = 'Fridays';
				$this->_lang['day: 6_plural']                                    = 'Saturdays';
				$this->_lang['month: 1']                                        = 'January';
				$this->_lang['month: 2']                                        = 'February';
				$this->_lang['month: 3']                                        = 'March';
				$this->_lang['month: 4']                                        = 'April';
				$this->_lang['month: 5']                                        = 'May';
				$this->_lang['month: 6']                                        = 'June';
				$this->_lang['month: 7']                                        = 'July';
				$this->_lang['month: 8']                                        = 'Augustus';
				$this->_lang['month: 9']                                        = 'September';
				$this->_lang['month: 10']                                        = 'October';
				$this->_lang['month: 11']                                        = 'November';
				$this->_lang['month: 12']                                        = 'December';
				$this->_lang['ordinal: 1']                                        = '1st';
				$this->_lang['ordinal: 2']                                        = '2nd';
				$this->_lang['ordinal: 3']                                        = '3rd';
				$this->_lang['ordinal: 4']                                        = '4th';
				$this->_lang['ordinal: 5']                                        = '5th';
				$this->_lang['ordinal: 6']                                        = '6th';
				$this->_lang['ordinal: 7']                                        = '7th';
				$this->_lang['ordinal: 8']                                        = '8th';
				$this->_lang['ordinal: 9']                                        = '9th';
				$this->_lang['ordinal: 10']                                        = '10th';
				$this->_lang['ordinal: 11']                                        = '11th';
				$this->_lang['ordinal: 12']                                        = '12th';
				$this->_lang['ordinal: 13']                                        = '13th';
				$this->_lang['ordinal: 14']                                        = '14th';
				$this->_lang['ordinal: 15']                                        = '15th';
				$this->_lang['ordinal: 16']                                        = '16th';
				$this->_lang['ordinal: 17']                                        = '17th';
				$this->_lang['ordinal: 18']                                        = '18th';
				$this->_lang['ordinal: 19']                                        = '19th';
				$this->_lang['ordinal: 20']                                        = '20th';
				$this->_lang['ordinal: 21']                                        = '21st';
				$this->_lang['ordinal: 22']                                        = '22nd';
				$this->_lang['ordinal: 23']                                        = '23rd';
				$this->_lang['ordinal: 24']                                        = '24th';
				$this->_lang['ordinal: 25']                                        = '25th';
				$this->_lang['ordinal: 26']                                        = '26th';
				$this->_lang['ordinal: 27']                                        = '27th';
				$this->_lang['ordinal: 28']                                        = '28th';
				$this->_lang['ordinal: 29']                                        = '29th';
				$this->_lang['ordinal: 30']                                        = '30th';
				$this->_lang['ordinal: 31']                                        = '31st';
				$this->_lang['ordinal: 32']                                        = '32nd';
				$this->_lang['ordinal: 33']                                        = '33rd';
				$this->_lang['ordinal: 34']                                        = '34th';
				$this->_lang['ordinal: 35']                                        = '35th';
				$this->_lang['ordinal: 36']                                        = '36th';
				$this->_lang['ordinal: 37']                                        = '37th';
				$this->_lang['ordinal: 38']                                        = '38th';
				$this->_lang['ordinal: 39']                                        = '39th';
				$this->_lang['ordinal: 40']                                        = '40th';
				$this->_lang['ordinal: 41']                                        = '41st';
				$this->_lang['ordinal: 42']                                        = '42nd';
				$this->_lang['ordinal: 43']                                        = '43rd';
				$this->_lang['ordinal: 44']                                        = '44th';
				$this->_lang['ordinal: 45']                                        = '45th';
				$this->_lang['ordinal: 46']                                        = '46th';
				$this->_lang['ordinal: 47']                                        = '47th';
				$this->_lang['ordinal: 48']                                        = '48th';
				$this->_lang['ordinal: 49']                                        = '49th';
				$this->_lang['ordinal: 50']                                        = '50th';
				$this->_lang['ordinal: 51']                                        = '51st';
				$this->_lang['ordinal: 52']                                        = '52nd';
				$this->_lang['ordinal: 53']                                        = '53rd';
				$this->_lang['ordinal: 54']                                        = '54th';
				$this->_lang['ordinal: 55']                                        = '55th';
				$this->_lang['ordinal: 56']                                        = '56th';
				$this->_lang['ordinal: 57']                                        = '57th';
				$this->_lang['ordinal: 58']                                        = '58th';
				$this->_lang['ordinal: 59']                                        = '59th';
				break;

			case 'nl':
				$this->_lang['elemMin: at_the_hour']                            = 'op het hele uur';
				$this->_lang['elemMin: after_the_hour_every_X_minute']            = 'elke minuut';
				$this->_lang['elemMin: after_the_hour_every_X_minute_plural']    = 'elke @1 minuten';
				$this->_lang['elemMin: every_consecutive_minute']                = 'elke opeenvolgende minuut';
				$this->_lang['elemMin: every_consecutive_minute_plural']        = 'elke opeenvolgende @1 minuten';
				$this->_lang['elemMin: every_minute']                            = 'elke minuut';
				$this->_lang['elemMin: between_X_and_Y']                        = 'van de @1 tot en met de @2';
				$this->_lang['elemMin: at_X:Y']                                    = 'Om @1:@2';
				$this->_lang['elemHour: past_X:00']                                = 'na @1:00';
				$this->_lang['elemHour: between_X:00_and_Y:59']                    = 'tussen @1:00 en @2:59';
				$this->_lang['elemHour: in_the_60_minutes_past_']                = 'in de 60 minuten na elk opeenvolgend uur';
				$this->_lang['elemHour: in_the_60_minutes_past__plural']        = 'in de 60 minuten na elke opeenvolgende @1 uren';
				$this->_lang['elemHour: past_every_consecutive_']                = 'na elk opeenvolgend uur';
				$this->_lang['elemHour: past_every_consecutive__plural']        = 'na elke opeenvolgende @1 uren';
				$this->_lang['elemHour: past_every_hour']                        = 'na elk uur';
				$this->_lang['elemDOM: the_X']                                    = 'de @1';
				$this->_lang['elemDOM: every_consecutive_day']                    = 'elke opeenvolgende dag';
				$this->_lang['elemDOM: every_consecutive_day_plural']            = 'elke opeenvolgende @1 dagen';
				$this->_lang['elemDOM: on_every_day']                            = 'op elke dag';
				$this->_lang['elemDOM: between_the_Xth_and_Yth']                = 'tussen de @1 en de @2';
				$this->_lang['elemDOM: on_the_X']                                = 'op de @1';
				$this->_lang['elemDOM: on_X']                                    = 'op @1';
				$this->_lang['elemMonth: every_X']                                = 'elke @1';
				$this->_lang['elemMonth: every_consecutive_month']                = 'elke opeenvolgende maand';
				$this->_lang['elemMonth: every_consecutive_month_plural']        = 'elke opeenvolgende @1 maanden';
				$this->_lang['elemMonth: between_X_and_Y']                        = 'van @1 tot @2';
				$this->_lang['elemMonth: of_every_month']                        = 'van elke maand';
				$this->_lang['elemMonth: during_every_X']                        = 'tijdens elke @1';
				$this->_lang['elemMonth: during_X']                                = 'tijdens @1';
				$this->_lang['elemYear: in_X']                                    = 'in @1';
				$this->_lang['elemYear: every_consecutive_year']                = 'elk opeenvolgend jaar';
				$this->_lang['elemYear: every_consecutive_year_plural']            = 'elke opeenvolgende @1 jaren';
				$this->_lang['elemYear: from_X_through_Y']                        = 'van @1 tot en met @2';
				$this->_lang['elemDOW: on_every_day']                            = 'op elke dag';
				$this->_lang['elemDOW: on_X']                                    = 'op @1';
				$this->_lang['elemDOW: but_only_on_X']                            = 'maar alleen als het plaatsvindt op @1';
				$this->_lang['separator_and']                                    = 'en';
				$this->_lang['separator_of']                                    = 'of';
				$this->_lang['day: 0_plural']                                    = 'zondagen';
				$this->_lang['day: 1_plural']                                    = 'maandagen';
				$this->_lang['day: 2_plural']                                    = 'dinsdagen';
				$this->_lang['day: 3_plural']                                    = 'woensdagen';
				$this->_lang['day: 4_plural']                                    = 'donderdagen';
				$this->_lang['day: 5_plural']                                    = 'vrijdagen';
				$this->_lang['day: 6_plural']                                    = 'zaterdagen';
				$this->_lang['month: 1']                                        = 'januari';
				$this->_lang['month: 2']                                        = 'februari';
				$this->_lang['month: 3']                                        = 'maart';
				$this->_lang['month: 4']                                        = 'april';
				$this->_lang['month: 5']                                        = 'mei';
				$this->_lang['month: 6']                                        = 'juni';
				$this->_lang['month: 7']                                        = 'juli';
				$this->_lang['month: 8']                                        = 'augustus';
				$this->_lang['month: 9']                                        = 'september';
				$this->_lang['month: 10']                                        = 'october';
				$this->_lang['month: 11']                                        = 'november';
				$this->_lang['month: 12']                                        = 'december';
				$this->_lang['ordinal: 1']                                        = '1e';
				$this->_lang['ordinal: 2']                                        = '2e';
				$this->_lang['ordinal: 3']                                        = '3e';
				$this->_lang['ordinal: 4']                                        = '4e';
				$this->_lang['ordinal: 5']                                        = '5e';
				$this->_lang['ordinal: 6']                                        = '6e';
				$this->_lang['ordinal: 7']                                        = '7e';
				$this->_lang['ordinal: 8']                                        = '8e';
				$this->_lang['ordinal: 9']                                        = '9e';
				$this->_lang['ordinal: 10']                                        = '10e';
				$this->_lang['ordinal: 11']                                        = '11e';
				$this->_lang['ordinal: 12']                                        = '12e';
				$this->_lang['ordinal: 13']                                        = '13e';
				$this->_lang['ordinal: 14']                                        = '14e';
				$this->_lang['ordinal: 15']                                        = '15e';
				$this->_lang['ordinal: 16']                                        = '16e';
				$this->_lang['ordinal: 17']                                        = '17e';
				$this->_lang['ordinal: 18']                                        = '18e';
				$this->_lang['ordinal: 19']                                        = '19e';
				$this->_lang['ordinal: 20']                                        = '20e';
				$this->_lang['ordinal: 21']                                        = '21e';
				$this->_lang['ordinal: 22']                                        = '22e';
				$this->_lang['ordinal: 23']                                        = '23e';
				$this->_lang['ordinal: 24']                                        = '24e';
				$this->_lang['ordinal: 25']                                        = '25e';
				$this->_lang['ordinal: 26']                                        = '26e';
				$this->_lang['ordinal: 27']                                        = '27e';
				$this->_lang['ordinal: 28']                                        = '28e';
				$this->_lang['ordinal: 29']                                        = '29e';
				$this->_lang['ordinal: 30']                                        = '30e';
				$this->_lang['ordinal: 31']                                        = '31e';
				$this->_lang['ordinal: 32']                                        = '32e';
				$this->_lang['ordinal: 33']                                        = '33e';
				$this->_lang['ordinal: 34']                                        = '34e';
				$this->_lang['ordinal: 35']                                        = '35e';
				$this->_lang['ordinal: 36']                                        = '36e';
				$this->_lang['ordinal: 37']                                        = '37e';
				$this->_lang['ordinal: 38']                                        = '38e';
				$this->_lang['ordinal: 39']                                        = '39e';
				$this->_lang['ordinal: 40']                                        = '40e';
				$this->_lang['ordinal: 41']                                        = '41e';
				$this->_lang['ordinal: 42']                                        = '42e';
				$this->_lang['ordinal: 43']                                        = '43e';
				$this->_lang['ordinal: 44']                                        = '44e';
				$this->_lang['ordinal: 45']                                        = '45e';
				$this->_lang['ordinal: 46']                                        = '46e';
				$this->_lang['ordinal: 47']                                        = '47e';
				$this->_lang['ordinal: 48']                                        = '48e';
				$this->_lang['ordinal: 49']                                        = '49e';
				$this->_lang['ordinal: 50']                                        = '50e';
				$this->_lang['ordinal: 51']                                        = '51e';
				$this->_lang['ordinal: 52']                                        = '52e';
				$this->_lang['ordinal: 53']                                        = '53e';
				$this->_lang['ordinal: 54']                                        = '54e';
				$this->_lang['ordinal: 55']                                        = '55e';
				$this->_lang['ordinal: 56']                                        = '56e';
				$this->_lang['ordinal: 57']                                        = '57e';
				$this->_lang['ordinal: 58']                                        = '58e';
				$this->_lang['ordinal: 59']                                        = '59e';
				break;
		}
	}

	final private function natlangPad2($number)
	{
		return (strlen($number) == 1 ? '0' : '').$number;
	}

	final private function natlangApply($id, $p1 = FALSE, $p2 = FALSE, $p3 = FALSE, $p4 = FALSE, $p5 = FALSE, $p6 = FALSE)
	{
		$txt = $this->_lang[$id];

		if($p1 !== FALSE)    $txt = str_replace('@1', $p1, $txt);
		if($p2 !== FALSE)    $txt = str_replace('@2', $p2, $txt);
		if($p3 !== FALSE)    $txt = str_replace('@3', $p3, $txt);
		if($p4 !== FALSE)    $txt = str_replace('@4', $p4, $txt);
		if($p5 !== FALSE)    $txt = str_replace('@5', $p5, $txt);
		if($p6 !== FALSE)    $txt = str_replace('@6', $p6, $txt);

		return $txt;
	}


	//
	// Function:    natlangRange
	//
	// Description:    Converts a range into natural language
	//
	// Parameters:
	//
	// Result:
	//

	final private function natlangRange($spec, $entryFunction, $p1 = FALSE)
	{
		$arrIntervals = array();
		foreach($spec['elements'] as $elem)
			$arrIntervals[] = call_user_func($entryFunction, $elem, $p1);

		$txt = "";
		for($index = 0; $index < count($arrIntervals); $index++)
			$txt .= ($index == 0 ? '' : ($index == (count($arrIntervals) - 1) ? ' '.$this->natlangApply('separator_and').' ' : ', ')).$arrIntervals[$index];
		return $txt;
	}


	//
	// Function:    natlangElementMinute
	//
	// Description:    Converts an entry from the minute specification to natural language.
	//

	final private function natlangElementMinute($elem)
	{
		if(!$elem['hasInterval'])
		{
			if($elem['number1'] == 0)    return $this->natlangApply('elemMin: at_the_hour');
			else                        return $this->natlangApply('elemMin: after_the_hour_every_X_minute'.($elem['number1'] == 1 ? '' : '_plural'), $elem['number1']);
		}

		$txt = $this->natlangApply('elemMin: every_consecutive_minute'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);
		if(($elem['number1'] != $this->_cronMinutes['rangeMin']) || ($elem['number2'] != $this->_cronMinutes['rangeMax']))
			$txt .= ' ('.$this->natlangApply('elemMin: between_X_and_Y', $this->natlangApply('ordinal: '.$elem['number1']), $this->natlangApply('ordinal: '.$elem['number2'])).')';
		return $txt;
	}


	//
	// Function:    natlangElementHour
	//
	// Description:    Converts an entry from the hour specification to natural language.
	//

	final private function natlangElementHour($elem, $asBetween)
	{
		if(!$elem['hasInterval'])
		{
			if($asBetween)    return $this->natlangApply('elemHour: between_X:00_and_Y:59', $this->natlangPad2($elem['number1']), $this->natlangPad2($elem['number1']));
			else            return $this->natlangApply('elemHour: past_X:00', $this->natlangPad2($elem['number1']));
		}

		if($asBetween)        $txt = $this->natlangApply('elemHour: in_the_60_minutes_past_'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);
		else                $txt = $this->natlangApply('elemHour: past_every_consecutive_'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);

		if(($elem['number1'] != $this->_cronHours['rangeMin']) || ($elem['number2'] != $this->_cronHours['rangeMax']))
			$txt .= ' ('.$this->natlangApply('elemHour: between_X:00_and_Y:59', $elem['number1'], $elem['number2']).')';
		return $txt;
	}


	//
	// Function:    natlangElementDayOfMonth
	//
	// Description:    Converts an entry from the day of month specification to natural language.
	//

	final private function natlangElementDayOfMonth($elem)
	{
		if(!$elem['hasInterval'])
			return $this->natlangApply('elemDOM: the_X', $this->natlangApply('ordinal: '.$elem['number1']));

		$txt = $this->natlangApply('elemDOM: every_consecutive_day'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);
		if(($elem['number1'] != $this->_cronHours['rangeMin']) || ($elem['number2'] != $this->_cronHours['rangeMax']))
			$txt .= ' ('.$this->natlangApply('elemDOM: between_the_Xth_and_Yth', $this->natlangApply('ordinal: '.$elem['number1']), $this->natlangApply('ordinal: '.$elem['number2'])).')';
		return $txt;
	}


	//
	// Function:    natlangElementDayOfMonth
	//
	// Description:    Converts an entry from the month specification to natural language.
	//

	final private function natlangElementMonth($elem)
	{
		if(!$elem['hasInterval'])
			return $this->natlangApply('elemMonth: every_X', $this->natlangApply('month: '.$elem['number1']));

		$txt = $this->natlangApply('elemMonth: every_consecutive_month'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);
		if(($elem['number1'] != $this->_cronMonths['rangeMin']) || ($elem['number2'] != $this->_cronMonths['rangeMax']))
			$txt .= ' ('.$this->natlangApply('elemMonth: between_X_and_Y', $this->natlangApply('month: '.$elem['number1']), $this->natlangApply('month: '.$elem['number2'])).')';
		return $txt;
	}


	//
	// Function:    natlangElementYear
	//
	// Description:    Converts an entry from the year specification to natural language.
	//

	final private function natlangElementYear($elem)
	{
		if(!$elem['hasInterval'])
			return $elem['number1'];

		$txt = $this->natlangApply('elemYear: every_consecutive_year'.($elem['interval'] == 1 ? '' : '_plural'), $elem['interval']);
		if(($elem['number1'] != $this->_cronMonths['rangeMin']) || ($elem['number2'] != $this->_cronMonths['rangeMax']))
			$txt .= ' ('.$this->natlangApply('elemYear: from_X_through_Y', $elem['number1'], $elem['number2']).')';
		return $txt;
	}


	final public function asNaturalLanguage()
	{
		$switchForceDateExplaination = FALSE;
		$switchDaysOfWeekAreExcluding = TRUE;


		// Generate Time String

		$txtMinutes                    = array();
		$txtMinutes[0]                = $this->natlangApply('elemMin: every_minute');
		$txtMinutes[1]                = $this->natlangElementMinute($this->_cronMinutes['elements'][0]);
		$txtMinutes[2]                = $this->natlangRange($this->_cronMinutes, array($this, 'natlangElementMinute'));

		$txtHours                    = array();
		$txtHours[0]                = $this->natlangApply('elemHour: past_every_hour');
		$txtHours[1]                = array();
		$txtHours[1]['between']        = $this->natlangRange($this->_cronHours, array($this, 'natlangElementHour'), TRUE);
		$txtHours[1]['past']        = $this->natlangRange($this->_cronHours, array($this, 'natlangElementHour'), FALSE);
		$txtHours[2]                = array();
		$txtHours[2]['between']        = $this->natlangRange($this->_cronHours, array($this, 'natlangElementHour'), TRUE);
		$txtHours[2]['past']        = $this->natlangRange($this->_cronHours, array($this, 'natlangElementHour'), FALSE);

		$classMinutes                = $this->getClass($this->_cronMinutes);
		$classHours                    = $this->getClass($this->_cronHours);

		switch($classMinutes.$classHours)
		{

			// Special case: Unspecified date + Unspecified month
			//
			// Rule: The language for unspecified fields is omitted if a more detailed field has already been explained.
			//
			// The minutes field always yields an explaination, at the very least in the form of 'every minute'. This rule states that if the
			// hour is not specified, it can be omitted because 'every minute' is already sufficiently clear.
			//

			case '00':
				$txtTime = $txtMinutes[0];
				break;


			// Special case: Fixed minutes and fixed hours
			//
			// The default writing would be something like 'every 20 minutes past 04:00', but the more common phrasing would be: At 04:20.
			//
			// We will switch ForceDateExplaination on, so that even a non-specified date yields an explaination (e.g. 'every day')
			//

			case '11':
				$txtTime = $this->natlangApply('elemMin: at_X:Y', $this->natlangPad2($this->_cronHours['elements'][0]['number1']), $this->natlangPad2($this->_cronMinutes['elements'][0]['number1']));
				$switchForceDateExplaination = TRUE;
				break;


			// Special case: Between :00 and :59
			//
			// If hours are specified, but minutes are not, then the minutes string will yield something like 'every minute'. We must the
			// differentiate the hour specification because the minutes specification does not relate to all minutes past the hour, but only to
			// those minutes between :00 and :59
			//
			// We will switch ForceDateExplaination on, so that even a non-specified date yields an explaination (e.g. 'every day')
			//

			case '01':
			case '02':
				$txtTime = $txtMinutes[$classMinutes].' '.$txtHours[$classHours]['between'];
				$switchForceDateExplaination = TRUE;
				break;


			// Special case: Past the hour
			//
			// If minutes are specified and hours are specified, then the specification of minutes is always limited to a maximum of 60 minutes
			// and always applies to the minutes 'past the hour'.
			//
			// We will switch ForceDateExplaination on, so that even a non-specified date yields an explaination (e.g. 'every day')
			//

			case '12':
			case '22':
			case '21':
				$txtTime = $txtMinutes[$classMinutes].' '.$txtHours[$classHours]['past'];
				$switchForceDateExplaination = TRUE;
				break;

			default:
				$txtTime = $txtMinutes[$classMinutes].' '.$txtHours[$classHours];
				break;
		}


		// Generate Date String

		$txtDaysOfMonth        = array();
		$txtDaysOfMonth[0]    = '';
		$txtDaysOfMonth[1]    = $this->natlangApply('elemDOM: on_the_X', $this->natlangApply('ordinal: '.$this->_cronDaysOfMonth['elements'][0]['number1']));
		$txtDaysOfMonth[2]    = $this->natlangApply('elemDOM: on_X', $this->natlangRange($this->_cronDaysOfMonth, array($this, 'natlangElementDayOfMonth')));

		$txtMonths            = array();
		$txtMonths[0]        = $this->natlangApply('elemMonth: of_every_month');
		$txtMonths[1]        = $this->natlangApply('elemMonth: during_every_X', $this->natlangApply('month: '.$this->_cronMonths['elements'][0]['number1']));
		$txtMonths[2]        = $this->natlangApply('elemMonth: during_X', $this->natlangRange($this->_cronMonths, array($this, 'natlangElementMonth')));

		$classDaysOfMonth    = $this->getClass($this->_cronDaysOfMonth);
		$classMonths        = $this->getClass($this->_cronMonths);

		if($classDaysOfMonth == '0')
			$switchDaysOfWeekAreExcluding = FALSE;

		switch($classDaysOfMonth.$classMonths)
		{

			// Special case: Unspecified date + Unspecified month
			//
			// Rule: The language for unspecified fields is omitted if a more detailed field has already been explained.
			//
			// The time fields always yield an explaination, at the very least in the form of 'every minute'. This rule states that if the date
			// is not specified, it can be omitted because 'every minute' is already sufficiently clear.
			//
			// There are some time specifications that do not contain an 'every' reference, but reference a specific time of day. In those cases
			// the date explaination is enforced.
			//

			case '00':
				$txtDate = '';
				break;

			default:
				$txtDate = ' '.$txtDaysOfMonth[$classDaysOfMonth].' '.$txtMonths[$classMonths];
				break;
		}


		// Generate Year String

		if ($this->_cronYears) {
			$txtYears            = array();
			$txtYears[0]        = '';
			$txtYears[1]        = ' '.$this->natlangApply('elemYear: in_X', $this->_cronYears['elements'][0]['number1']);
			$txtYears[2]        = ' '.$this->natlangApply('elemYear: in_X', $this->natlangRange($this->_cronYears, array($this, 'natlangElementYear')));

			$classYears            = $this->getClass($this->_cronYears);
			$txtYear = $txtYears[$classYears];
		}


		// Generate DaysOfWeek String

		$collectDays = 0;
		foreach($this->_cronDaysOfWeek['elements'] as $elem)
		{
			if($elem['hasInterval'])
				for($x = $elem['number1']; $x <= $elem['number2']; $x += $elem['interval'])
					$collectDays |= pow(2, $x);
			else
				$collectDays |= pow(2, $elem['number1']);
		}
		if($collectDays == 127)    // * all days
		{
			if(!$switchDaysOfWeekAreExcluding)
				$txtDays = ' '.$this->natlangApply('elemDOM: on_every_day');
			else
				$txtDays = '';
		}
		else
		{
			$arrDays = array();
			for($x = 0; $x <= 6; $x++)
				if($collectDays & pow(2, $x))
					$arrDays[] = $x;
			$txtDays = '';
			for($index = 0; $index < count($arrDays); $index++)
				$txtDays .= ($index == 0 ? '' : ($index == (count($arrDays) - 1) ? ' '.$this->natlangApply($switchDaysOfWeekAreExcluding ? 'separator_or' : 'separator_and').' ' : ', ')).$this->natlangApply('day: '.$arrDays[$index].'_plural');
			if($switchDaysOfWeekAreExcluding)    $txtDays = ' '.$this->natlangApply('elemDOW: but_only_on_X', $txtDays);
			else                                $txtDays = ' '.$this->natlangApply('elemDOW: on_X', $txtDays);
		}

		$txtResult = ucfirst($txtTime).$txtDate.$txtDays;

		if (isset($txtYear)) {
			if ($switchDaysOfWeekAreExcluding) {
				$txtResult = ucfirst($txtTime).$txtDate.$txtYear.$txtDays;
			} else {
				$txtResult = ucfirst($txtTime).$txtDate.$txtDays.$txtYear;
			}
		}

		return $txtResult.'.';
	}
}


class csd_parser

{





    /**

     * Cron scheduling definition(s) string

     *

     * @var string

     */

    protected $csds;

    /**

     * Time set constructino

     *

     * @var int

     */

    protected $base_time;

    /**

     * Array with parsed time data

     *

     * @var array

     */

    protected $times = array();

    /**

     * Array with known times relative to base_time

     *

     * @var array

     */

    protected $known = array();





    /**

     * Names of the fields

     *

     * @var str[]

     */

    static protected $fields = array('minute', 'hour', 'day', 'month', 'weekday', 'year');

    /**

     * Mapping for month-names and day names

     *

     * @var array

     */

    static protected $mapping

        = array(

            'day'   => array('sun'    => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6,

                             'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,

                             'friday' => 5, 'saturday' => 6),

            'month' => array('jan'     => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7,

                             'aug'     => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,

                             'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'june' => 6, 'july' => 7,

                             'august'  => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12)

        );

    /**

     * Long month mapping

     *   0 doubles for 12 (for 'january - 1')

     *

     * @var bool[]

     */

    static protected $long = array(true, true, false, true, false, true, false, true, true, false, true, false, true);





    /**

     * Create a new csd_parser

     *

     * @param  mixed $csd  Array of definition(s) or a (multiline) string with definition(s)

     * @param  mixed $time Set a custom base time for this parser (unix timestamp or parsable string)

     *

     * @throws Exception   If none of the definitions were valid

     * @return csd_parser

     */

    public function __construct($csd, $time = null)

    {

        $this->csds  = is_array($csd) ? implode("\n", $csd) : $csd;

        $this->times = self::prep_csds($csd);

        if ($this->times === false) {

            throw new Exception(__METHOD__.': No valid definition(s) provided. Check error log for details.');

        }

        $this->base_time = self::parse_time($time);

    }





    /**

     * Calculate a single runtime for the provided definition(s)

     *

     * @param  mixed $which Which occurence ('last', 'next' or an integer: 0 for next, -1 for last, -2 for the one before that&#65533;)

     *                      Additionaly when you provide a string: it will be than be treated as a time (unix stamp or parsable

     *                      string) up until which to calculate times. This results in an array of times being returned.

     * @param  mixed $time  Calculate relative to this time instead of the base time of this parser

     *

     * @return mixed The unix timestamp of the runtime (or null if it doesn't exist)

     */

    public function get($which = 'next', $time = null)

    {

        $which = $which !== 'next' ? $which !== 'prev' ? $which !== 'last' ? $which : -1 : -1 : 0;

        if (!is_int($which)) {

            if (is_string($which)) {

                $till = preg_match('/^\d+$/', $which) ? $which * 1 : strtotime($which);

            }

            if (!isset($till) || empty($till)) {

                $trace = debug_backtrace();

                trigger_error(

                    __METHOD__

                    .": Incorrect value given for \$which in {$trace[0]['file']} on line {$trace[0]['line']}. . Using \"next\" instead.",

                    E_USER_WARNING

                );

                $which = 0;

            }

        }

        if ($time === null) {

            $time = $this->base_time;

        } else {

            $time = self::parse_time($time);

        }

        $store = $time === $this->base_time;

        if (isset($till)) {

            $times = array();

            $up    = $till >= $time ? true : false;

            $index = $up ? 0 : -1;

            do {

                if ($store && isset($this->known[$index])) {

                    $time = $this->known[$index];

                } else {

                    $time = self::find_time($this, $time, $up);

                    if ($time === null) {

                        break;

                    }

                    if ($store) {

                        $this->known[$index] = $time;

                    }

                }

                $times[] = $time;

                $time    = $up ? $time + 60 : $time - 60;

                $index   = $up ? $index + 1 : $index - 1;

            } while (($up && $time < $till) || (!$up && $time > $till));

            array_pop($times);

            return $times;

        } else {

            $which = (int)$which;

            $up    = $which >= 0 ? true : false;

            $runs  = $up ? $which + 1 : $which * -1;

            for ($i = 1; $i <= $runs; $i++) {

                if ($store && isset($this->known[$up ? $i - 1 : -$i])) {

                    $time = $this->known[$up ? $i - 1 : -$i];

                } else {

                    $time = self::find_time($this, $time, $up);

                    if ($time === null) {

                        break;

                    }

                    if ($store) {

                        $this->known[$up ? $i - 1 : -$i] = $time;

                    }

                }

                if ($i !== $runs) {

                    $time = $up ? $time + 60 : $time - 60;

                }

            }

        }

        return $time;

    }





    /**

     * Calculate a single runtime for the given definition(s)

     *

     * @param  mixed $csd   Array of definition(s) or a (multiline) string with definition(s)

     * @param  mixed $which Which occurence ('last', 'next' or an integer: 0 for next, -1 for last, -2 for the one before that&#65533;)

     *                      Additionaly when you provide a string: it will be than be treated as a time (unix stamp or parsable

     *                      string) up until which to calculate times. This results in an array of times being returned.

     * @param  mixed $time  Set a custom base time to calculate agains (unix timestamp or parsable string)

     *

     * @return int   The unix timestamp of the runtime (or null if it doesn't exist)

     */

    static public function calc($csd, $which = 'next', $time = null)

    {

        $parser = new self($csd, $time);

        return $parser->get($which);

    }





    /**

     * Calculate a single time for a parser

     *

     * @param csd_parser $parser The parser for which to calculate

     * @param int        $time   Calculate relative to this time

     * @param bool       $up     Find next

     *

     * @return int|null

     */

    static protected function find_time(csd_parser $parser, $time, $up = true)

    {

        $next = null;

        foreach ($parser->times as $time_data) {

            $option = self::find_time_l1($time_data, $time, $up);

            if ($option !== null) {

                if ($next === null) {

                    $next = $option;

                } elseif ($up) {

                    if ($next > $option) {

                        $next = $option;

                    }

                } else {

                    if ($next < $option) {

                        $next = $option;

                    }

                }

            }

        }

        return $next;

    }





    /**

     * Find a time

     *

     * @param mixed $time_data

     * @param mixed $time

     * @param mixed $up

     *

     * @return int|null

     */

    static protected function find_time_l1($time_data, $time, $up = true)

    {

        if (is_array($time)) {

            $time = mktime($time[1], $time[0], 0, $time[3], $time[2], $time[5]);

        }

        $rel_times = explode(' ', date('i H d m w Y', $time));

        foreach ($rel_times as $key => $val) {

            $rel_times[$key] = (int)$val;

        }

        $with  = $rel_times;

        $limit = 100;

        if (!self::find_time_l2($time_data, $with, 5, $up)) {

            return null;

        }

        if (!self::find_time_l2($time_data, $with, 3, $up)) {

            return null;

        }

        do {

            if (!self::find_time_l2($time_data, $with, 2, $up)) {

                return null;

            }

            if (!self::find_time_l2($time_data, $with, 1, $up)) {

                return null;

            }

            if (!self::find_time_l2($time_data, $with, 0, $up)) {

                return null;

            }

            $weekday = (int)date('w', mktime($with[1], $with[0], 0, $with[3], $with[2], $with[5]));

            $with[4] = $weekday;

            if (self::find_time_l2($time_data, $with, 4, $up)) {

                $offset = $with[4] - $weekday;

            } else {

                if ($up) {

                    $offset = $with[4] + (7 - $weekday);

                } else {

                    $offset = -($weekday + (7 - $with[4]));

                }

            }

            $with[2] += $offset;

        } while ($offset !== 0 && --$limit !== 0);

        return mktime($with[1], $with[0], 0, $with[3], $with[2], $with[5]);

    }





    /**

     * Flood time array with the closest value of a field from a time data array

     *

     * @param  array $for      Time data

     * @param  array $with     Time array (to be set)

     * @param  int   $field_id Field ID

     * @param  bool  $up       Whether direction is up or not

     * @param  bool  $force    Whether to force update (use after manually setting the field's value);

     *

     * @return bool  Whether there actually is a possible time or not in that direction

     */

    static protected function find_time_l2($for, &$with, $field_id, $up = true, $force = false)

    {

        if ($field_id === 5 && !isset($for[$field_id])) {

            return true;

        } # years are optional

        $index = $up ? $for[$field_id]->index : array_reverse($for[$field_id]->index);

        $curr  = $with[$field_id];

        $spill = true;

        $value = null;

        # scan for next option without spill

        if ($up) {

            foreach ($index as $value) {

                if ($value >= $curr) {

                    if ($field_id !== 2

                        || # not a day,

                        $value < 29

                        || (self::$long[$with[3]] && $value <= 31)

                        || ($with[3] !== 2 && $value <= 30)

                        || (self::ly($with[5]))

                    ) {

                        $spill = false;

                        if ($value === $curr && !$force) {

                            return true;

                        } else {

                            break;

                        }

                    }

                }

            }

        } else {

            foreach ($index as $value) {

                if ($value <= $curr) {

                    $spill = false;

                    if ($value === $curr && !$force) {

                        return true;

                    } else {

                        break;

                    }

                }

            }

        }

        # set value, and spill if needed

        if (!$spill) {

            $with[$field_id] = $value;

            if ($field_id === 4) {

                return true;

            }

        } else {

            if ($field_id > 3) {

                $with[$field_id] = $value;

                return false;

            }

            for ($i = $field_id + 1; $i <= 5; $i++) {

                if ($i === 4) {

                    continue;

                }

                $with[$i] = $up ? $with[$i] + 1 : $with[$i] - 1;

                $break    = self::make_valid($with, $i, $up);

                self::find_time_l2($for, $with, $i, $up);

                if ($break) {

                    break;

                }

            }

            $with[$field_id] = $index[0];

            self::make_valid($with, $field_id, $up);

        }

        # finally, adjust lower params

        for ($i = $field_id - 1; $i >= 0; $i--) {

            switch ($i) {

                case 3:

                    $with[3] = $up ? 1 : 12;

                    break;

                case 2:

                    if ($up) {

                        $with[2] = 1;

                        break;

                    }

                    if (self::$long[$with[3]]) {

                        $with[2] = 31;

                    } # long month: 31

                    elseif ($with[3] !== 2) {

                        $with[2] = 30;

                    } elseif (self::ly($with[5])) {

                        $with[2] = 29;

                    } # feb in a leapyear: 29

                    else {

                        $with[2] = 28;

                    }

                    break;

                case 1:

                    $with[1] = $up ? 0 : 23;

                    break;

                case 0:

                    $with[0] = $up ? 0 : 59;

                    break;

            }

        }

        return true;

    }





    /**

     * Get the next valid value for a field in a certain direction

     *

     * @param  array $with     Time array (to be set)

     * @param  int   $field_id Field ID

     * @param  bool  $up       Whether direction is up or not

     *

     * @return bool  Found without need for spill

     */

    static protected function make_valid(&$with, $field_id, $up)

    {

        $want =& $with[$field_id];

        switch ($field_id) {

            case 4:

                if ($want < 0 || $want > 6) {

                    $want = $up ? 0 : 6;

                    return false;

                }

                break;

            case 3:

                if ($want < 1 || $want > 12) {

                    $want = $up ? 1 : 12;

                    return false;

                }

                break;

            case 2:

                if ($up) {

                    if ($want < 29 || (self::$long[$with[3]] && $want <= 31) || ($with[3] !== 2 && $want <= 30)

                        || (self::ly($with[5]))

                    ) {

                        break;

                    }

                    $want = 1;

                    return false;

                } elseif ($want < 1) {

                    if (self::$long[$with[3] - 1]) {

                        $want = 31;

                    } elseif ($with[3] !== 3) {

                        $want = 30;

                    } elseif (self::ly($with[5])) {

                        $want = 29;

                    } else {

                        $want = 28;

                    }

                    return false;

                }

                break;

            case 1:

                if ($want < 0 || $want > 23) {

                    $want = $up ? 0 : 23;

                    return false;

                }

                break;

            case 0:

                if ($want < 0 || $want > 59) {

                    $want = $up ? 0 : 59;

                    return false;

                }

                break;

        }

        return true;

    }





    /**

     * Prepare definitions

     *

     * @param  mixed      Array of definition(s) or a (multiline) string with definition(s)

     *

     * @return stdClass[] Array with object with timing details

     */

    static protected function prep_csds($csds)

    {

        if (!is_array($csds)) {

            $csds = preg_split('/\s*(?:\r\n|\r|\n)\s*/', trim($csds), null, PREG_SPLIT_NO_EMPTY);

        }

        $index = 0;

        $data  = array();

        foreach ($csds as $line => $csd) {

            $fields = preg_split('/\s+/', $csd, null, 1);

            $times  = array();

            # convert special statements

            if (!isset($fields[1])) {

                switch ($fields[0]) {

                    case '@yearly':

                    case '@annually':

                        $csd = '0 0 1 1 *';

                        break;

                    case '@monthly':

                        $csd = '0 0 1 * *';

                        break;

                    case '@weekly':

                        $csd = '0 0 * * 0';

                        break;

                    case '@daily':

                    case '@midnight':

                        $csd = '0 0 * * *';

                        break;

                    case '@hourly':

                        $csd = '0 * * * *';

                        break;

                }

                $fields = explode(' ', $csd);

            }

            if (!isset($fields[4]) || isset($fields[6])) {

                $trace = debug_backtrace();

                trigger_error(

                    "{$trace[1]['function']}: Skipping incorrect definition '".implode(' ', $fields)

                    ."' found on line $line of the definitions provided in {$trace[1]['file']} on line {$trace[1]['line']}.",

                    E_USER_WARNING

                );

                continue;

            }

            # process the separate fields

            foreach ($fields as $field_id => $field) {

                $error = false;

                $type  = self::$fields[$field_id];

                $field = str_replace('?', '*', $field);

                if (isset(self::$mapping[$type])) { # if we have a mapping replace according to it

                    $trace = debug_backtrace();

                    $field = str_ireplace(

                        array_keys(self::$mapping[$type]), array_values(self::$mapping[$type]), $field

                    );

                }

                if ($field_id === 4) {

                    $old   = $field;

                    $field = strtr($field, array('6-7' => '6,0', '-7' => '-6,0', '7' => '0'));

                    if ($old !== $field) {

                        /** @noinspection PhpUndefinedVariableInspection */

                        trigger_error(

                            "{$trace[1]['function']}: Use of incorrect value for (and/or place of) 7, 'sun' or 'sunday' in the definition-field value for $type ('"

                            .$fields[$field_id]."' in definition '".implode(' ', $fields)

                            ."') on line $line of the definitions provided in {$trace[1]['file']} on line {$trace[1]['line']}.",

                            E_USER_NOTICE

                        );

                    }

                }

                $sets                    = preg_split('/,/', $field, null);

                $times[$field_id]        = new stdClass();

                $times[$field_id]->index = array();

                # process the sets in the fields

                foreach ($sets as $set) {

                    if ($set === '' || !self::parse_set($times[$field_id], $type, $set)) {

                        $error = true;

                    }

                }

                if ($error) {

                    $trace = debug_backtrace();

                    if (empty($times[$field_id]->index)) {

                        trigger_error(

                            "{$trace[1]['function']}: Skipping incorrect definition '".implode(' ', $fields)."' due to unparseable definition-field value for $type ('"

                            .$fields[$field_id]

                            ."') on line $line of the definitions provided in {$trace[1]['file']} on line {$trace[1]['line']}.",

                            E_USER_WARNING

                        );

                        continue 2;

                    } else {

                        trigger_error(

                            "{$trace[1]['function']}: Ignored incorrect part(s) of the definition-field value for $type ('"

                            .$fields[$field_id]."' in definition '".implode(' ', $fields)

                            ."') on line $line of the definitions provided in {$trace[1]['file']} on line {$trace[1]['line']}.",

                            E_USER_NOTICE

                        );

                    }

                }

                $times[$field_id]->index = array_keys($times[$field_id]->index);

                sort($times[$field_id]->index);

            }

            # apparently it all went quite well

            $data[$index] = $times;

            $index++;

        }

        if (empty($data)) {

            return false;

        }

        return $data;

    }





    /**

     * Parse a single set of a field of an definition into $data

     *

     * @param stdClass $data The time data object

     * @param string   $type The type of field we're parsing here

     * @param string   $set  The set that is to be parsed

     *

     * @return bool

     */

    static protected function parse_set(&$data, $type, $set)

    {

        $min = 0;

        $max = 0;

        switch ($type) {

            case 'minute':

                $min = 0;

                $max = 59;

                break;

            case 'hour':

                $min = 0;

                $max = 23;

                break;

            case 'day':

                $min = 1;

                $max = 31;

                break;

            case 'month':

                $min = 1;

                $max = 12;

                break;

            case 'weekday':

                $min = 0;

                $max = 6;

                break;

            case 'year':

                $min = 1970;

                $max = 2099;

                break;

        }

        # parse default definitions

        if ($set === '*') { # asterisk

            $add = array_fill($min, $max - $min + 1, true);

        } elseif (preg_match('/^\d+$/', $set)) { # single digit

            if ($set >= $min && $set <= $max) {

                $add = array_fill($set, 1, true);

            }

        } elseif (preg_match('/^\d+\-\d+$/', $set)) { # digit range

            $set = explode('-', $set);

            if ($set[0] < $min) {

                $set[0] = $min;

            }

            if ($set[1] > $max) {

                $set[1] = $max;

            }

            if ($set[0] <= $set[1]) {

                $add = array_fill($set[0], $set[1] - $set[0] + 1, true);

            }

        } elseif (preg_match('/^(\*|(\d+\-\d+))\/\d+$/', $set)) { # incremental range

            $set = explode('/', $set);

            if ($set[0] === '*') {

                $set[0] = array($min, $max);

            } else {

                $set[0] = explode('-', $set[0]);

                if ($set[0][0] < $min) {

                    $set[0][0] = $min;

                }

                if ($set[0][1] > $max) {

                    $set[0][1] = $max;

                }

            }

            if ($set[0][0] <= $set[0][1]) {

                for ($i = $set[0][0]; $i <= $set[0][1]; $i += $set[1]) {

                    $add[(int)$i] = true;

                }

            }

        }

        # if the default definition yielded result

        if (isset($add)) {

            foreach ($add as $key => $val) {

                $data->index[$key] = 1;

            }

            return true;

        }

        return false;

    }





    /**

     * Parse a given time

     *

     * @param  mixed $time Unix timestamp or parseble string

     *

     * @return int Unix timestamp

     */

    static protected function parse_time($time)

    {

        if ($time === null) {

            $time = time();

        } elseif (is_string($time) || is_numeric($time)) {

            if (preg_match('/^\d*$/', $time)) {

                $time = (int)$time;

            } else {

                $time = @strtotime($time);

            }

        }

        if (!is_int($time)) {

            $trace = debug_backtrace();

            trigger_error(

                "{$trace[1]['function']}: Incorrect value given for \$time in {$trace[1]['file']} on line {$trace[1]['line']}. Using current time instead.",

                E_USER_NOTICE

            );

            $time = time();

        }

        return $time;

    }





    /**

     * Check if a year is a leap year

     *

     * @param int $year The year to be checked

     *

     * @return bool Whether the year is a leap year

     */

    static protected function ly($year)

    {

        if ($year % 100 === 0) {

            return $year % 400 === 0 ? true : false;

        }

        if ($year % 4 === 0) {

            return true;

        }

        return true;

    }



}


$cron_string = $argv[1];
$as_natural_language_parser = CronSchedule::fromCronString(trim($cron_string));
$next_prev_parser = new csd_parser($cron_string);

$as_natural_language = $as_natural_language_parser->asNaturalLanguage();
$next_run_date = date('M j h:i', $next_prev_parser->get('next'));
$prev_run_date = date('M j h:i', $next_prev_parser->get('prev'));


print $as_natural_language."|".$next_run_date."|".$prev_run_date;





