<?php
/**
 * Calendar functions.
 *
 * @package Welcart
 */

/**
 * Get Today.
 *
 * @return array
 */
function getToday() {
	$time        = current_time( 'timestamp' );
	$datetimestr = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() ) );
	$hour        = (int) substr( $datetimestr, 11, 2 );
	$minute      = (int) substr( $datetimestr, 14, 2 );
	$second      = (int) substr( $datetimestr, 17, 2 );
	$month       = (int) substr( $datetimestr, 5, 2 );
	$day         = (int) substr( $datetimestr, 8, 2 );
	$year        = (int) substr( $datetimestr, 0, 4 );
	$timestamp   = mktime( $hour, $minute, $second, $month, $day, $year );

	$dateAry = getdate( $timestamp );
	return array( $dateAry['year'], $dateAry['mon'], $dateAry['mday'] );
}

/**
 * Get week.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @return int Week number.
 */
function getWeek( $year, $month, $day ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month, $day, $year ) );
	return $dateAry['wday'];
}

/**
 * Get last day.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @return int Last day.
 */
function getLastDay( $year, $month ) {
	list( $nextyy, $nextmm ) = getNextMonth( $year, $month );
	$dateAry = getdate( mktime( 0, 0, 0, $nextmm, 0, $nextyy ) );
	return $dateAry['mday'];
}

/**
 * Get week.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @return bool.
 */
function isToday( $year, $month, $day ) {
	list( $todayyy, $todaymm, $todaydd ) = getToday();
	if ( $year == $todayyy && $month == $todaymm && $day == $todaydd ) {
		return true;
	}
	return false;
}

/**
 * Get next day.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @return array
 */
function getNextDay( $year, $month, $day ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month, $day + 1, $year ) );
	return array( $dateAry['year'], $dateAry['mon'], $dateAry['mday'] );
}

/**
 * Get previous day.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @return array
 */
function getPrevDay( $year, $month, $day ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month, $day - 1, $year ) );
	return array( $dateAry['year'], $dateAry['mon'], $dateAry['mday'] );
}

/**
 * Get next month.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @return array
 */
function getNextMonth( $year, $month ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month + 1, 1, $year ) );
	return array( $dateAry['year'], $dateAry['mon'] );
}

/**
 * Get previous month.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @return array
 */
function getPrevMonth( $year, $month ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month - 1, 1, $year ) );
	return array( $dateAry['year'], $dateAry['mon'] );
}

/**
 * Get after month.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @param int $n Number of months.
 * @return array
 */
function getAfterMonth( $year, $month, $day, $n ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month + $n, $day, $year ) );
	return array( $dateAry['year'], $dateAry['mon'], $dateAry['mday'] );
}

/**
 * Get before month.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @param int $n Number of months.
 * @return array
 */
function getBeforeMonth( $year, $month, $day, $n ) {
	$dateAry = getdate( mktime( 0, 0, 0, $month - $n, $day, $year ) );
	return array( $dateAry['year'], $dateAry['mon'], $dateAry['mday'] );
}
