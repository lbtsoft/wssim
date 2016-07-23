<?php

/**
 * SIM Web Services
 */
require_once $CFG->dirroot.'/grade/lib.php';
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . "/gradelib.php");
//require_once('/usr/share/moodle/config.php');
//require_once('/usr/share/moodle/lib/gradelib.php');

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

define("MEAN_OF_GRADES", 0);
define("WEIGHTED_MEAN", 10);
define("SIMPLE_WEIGHTED_MEAN", 11);


class local_wssim_external extends external_api {
		
		// needed for get_gradebook
		private static $categories;
		private static $categoriesout; 
		//private static $categorytotals = array();
		private static $itemsout;
		private static $activation_trigger;

    // Returns description of method parameters
    public static function hello_world_parameters() {
      return new external_function_parameters(
				array('welcomemessage' => new external_value(PARAM_TEXT, 'The test message. By default it is "You have successfully connected to SIM Web Services on your Moodle!"', VALUE_DEFAULT, 'You have successfully connected to SIM Web Services on your Moodle!'))
      );
    }
    // Returns welcome message
    public static function hello_world($welcomemessage) {
        global $USER;
        //Parameter validation -- REQUIRED
        $params = self::validate_parameters(self::hello_world_parameters(), array('welcomemessage' => $welcomemessage));
        //Context validation -- OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);
        //Capability checking -- OPTIONAL but in most web service it should present
        if (!has_capability('moodle/user:viewdetails', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }
        return $params['welcomemessage']; // . $USER->firstname ;
    }
    // Returns description of method result value
    public static function hello_world_returns() {
        return new external_value(PARAM_TEXT, 'The welcome message'); // + user first name');
    }

// GET COURSE NAMES /////////////////////////////////////////////////////////////////////////////////////////////

    // Returns description of method parameters
    public static function get_course_names_parameters() {
        return new external_function_parameters(
					array()
        );
    }

    // Returns course names and ids
    public static function get_course_names() {
        global $DB;
        //Parameter validation
        $params = self::validate_parameters(self::get_course_names_parameters(), array());
				$sql = "SELECT id,fullname,shortname,idnumber FROM {course} WHERE id <> 1 ORDER BY fullname";
				// 10x faster to use get_records_sql() than to call get_courses() and then filter out just the four fields we want
				$records = $DB->get_records_sql($sql);
				foreach($records as $record):
					$courses[] = (array)$record;
				endforeach;
				return json_encode($courses);
    }
		
    // Returns description of method result value
    public static function get_course_names_returns() {
        return new external_value(PARAM_TEXT, 'For each course, returns json array of id,fullname,shortname,idnumber.');
    }

// GET COURSE TEACHERS ///////////////////////////////////////////////////////////////////////////////////////////

    // Returns description of method parameters
    public static function get_course_teachers_parameters() {
        return new external_function_parameters(
					array('courseid' => new external_value(PARAM_TEXT, 'The value in the Course ID number field of Moodle\'s course settings page', VALUE_DEFAULT, ''),
								'courseshortname' => new external_value(PARAM_TEXT, 'The value in the Course short name field of Moodle\'s course settings page', VALUE_DEFAULT, ''))
        );
    }
    // Returns teacher's username, lastname, firstname
    public static function get_course_teachers($courseid,$courseshortname) {
			global $DB;
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::get_course_teachers_parameters(),
					array('courseid' => $courseid, 'courseshortname' => $courseshortname)
			);

			// build the query
			$sql = "SELECT DISTINCT usr.username, usr.lastname, usr.firstname  
								FROM {course} c 
								JOIN {context} cx ON c.id = cx.instanceid AND cx.contextlevel = '50' 
								JOIN {role_assignments} ra ON cx.id = ra.contextid 
								JOIN {role} r ON ra.roleid = r.id 
								JOIN {user} usr ON ra.userid = usr.id 
							 WHERE (r.name = 'teacher' 
										 OR r.name='editingteacher' 
										 OR r.shortname = 'teacher' 
										 OR r.shortname='editingteacher')";

			if(!empty($params['courseid'])):
				$sql .= " AND c.idnumber = :parm";
				$parm = $params['courseid'];
			elseif(!empty($params['courseshortname'])):
				$sql .= " AND c.shortname = :parm";
				$parm = $params['courseshortname'];
			endif;

			if( $parm ):
				$records = $DB->get_records_sql($sql, array('parm'=>$parm));
				foreach($records as $record):
					$teachers[] = (array)$record;
				endforeach;

				return json_encode($teachers);

			endif;
    }

    // Returns description of method result value
    public static function get_course_teachers_returns() {
        return new external_value(PARAM_TEXT, 'Returns a json array of the teachers in the specified course. Each element in the teachers array is a json array of: username, lastname, firstname.');
    }

// GET COURSE ACTIVITY ///////////////////////////////////////////////////////////////////////////////////////////
// Date of last access for one or more students in a specified course

    // Returns description of method parameters
    public static function get_course_activity_parameters() {
        return new external_function_parameters(
					array(
						'courseid' => new external_value(PARAM_TEXT, 'The value in the Course ID number field of Moodle\'s course settings page', VALUE_DEFAULT, ''),
						'courseshortname' => new external_value(PARAM_TEXT, 'The value in the Course short name field of Moodle\'s course settings page', VALUE_DEFAULT, ''),
						'studentids' => new external_value(PARAM_TEXT, 'A csv list of student ID numbers (PENs). Omit to get date of last access for all students.', VALUE_DEFAULT, '')
					)
        );
    }
    // Returns date of last access, indexed by PEN (or whatever is in the student ID number field)
    public static function get_course_activity($courseid,$courseshortname,$studentids) {
			global $DB;
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::get_course_activity_parameters(),
					array('courseid' => $courseid, 'courseshortname' => $courseshortname, 'studentids' => $studentids)
			);

			// build the query
			$crs_parms = array();
			$stu_parms = array();
			
			if( !empty($params['courseid']) ):
				$crs = "c.idnumber = ?";
				$crs_parms[] = $params['courseid'];
			elseif( !empty($params['courseshortname']) ):
				$crs = "c.shortname = ?";
				$crs_parms[] = $params['courseshortname'];
			endif;

			if( !empty($params['studentids']) ):
				// we can't pass a csv list as a parameter because it is seen as a single value with embedded commas
				// -> convert the csv list to an array
				// -> use get_in_or_equal() to create the list
				$studentids = explode(',',$params['studentids']);
				list($insql, $stu_parms) = $DB->get_in_or_equal($studentids);
				$stu = "usr.idnumber $insql";
			endif;

			if( $crs ):
				// just looking for students who are in the specified course
				$sql .= "SELECT usr.idnumber AS studentid, DATE( FROM_UNIXTIME( la.timeaccess ) ) AS lastaccessdate
									 FROM {user_lastaccess} la
						 			 JOIN {course} c ON la.courseid = c.id
						 			 JOIN {user} usr ON la.userid = usr.id
						 			WHERE $crs";
				if( $stu ):
					// limit the query to the specified students who are in the specified course
					$sql .= " AND $stu";
				endif;
			elseif( $stu ):
				// looking for the specified students regardless of course
				$sql .= "SELECT usr.idnumber AS studentid, DATE( FROM_UNIXTIME( la.timeaccess ) ) AS lastaccessdate
									 FROM {user_lastaccess} la
						 			 JOIN {user} usr ON la.userid = usr.id 
									WHERE $stu";
			endif;
			
			// merge the parameter arrays into a single array
			$parms = array_merge($crs_parms, $stu_parms);

			if( $sql ):
				$records = $DB->get_records_sql($sql,$parms);
				foreach($records as $record):
					$activity[$record->studentid] = $record->lastaccessdate;
				endforeach;

				return json_encode($activity);

			endif;
    }
		
    // Returns description of method result value
    public static function get_course_activity_returns() {
        return new external_value(PARAM_TEXT, 'For all students in a specified course (by short name or by ID number), returns date of last access in a json array indexed by student ID number (e.g. PEN). Returned data can be limited to specific student(s) by specifying a csv list of student ID numbers, and omitting a course specification returns date of last access in any course for the specified student(s).');
    }

// GET STUDENT ACTIVITY ///////////////////////////////////////////////////////////////////////////////////////////
// Date of last access for one student in one or more courses

    // Returns description of method parameters
    public static function get_student_activity_parameters() {
        return new external_function_parameters(
					array(
						'studentid' => new external_value(PARAM_TEXT, 'A student ID number (PEN).', VALUE_DEFAULT, ''),
						'courseids' => new external_value(PARAM_TEXT, 'A csv list of single-quoted course ID numbers (The value in the Course ID number field of Moodle\'s course settings page)', VALUE_DEFAULT, '')
					)
        );
    }
    // Returns date of last access, indexed by PEN (or whatever is in the student ID number field)
    public static function get_student_activity($studentid,$courseids) {
			global $DB;
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::get_student_activity_parameters(),
					array('studentid' => $studentid, 'courseids' => $courseids)
			);
			
			// build the query
			if( !empty($params['courseids']) ):
				// we can't pass a csv list as a parameter because it is seen as a single value with embedded commas
				// -> convert the csv list to an array
				// -> use get_in_or_equal() to create the list
				// -> original specification for this function was to pass singlequote-delimited courseids
				$temp = str_replace("'","",$params['courseids']); // make sure the single quotes are gone
				$courseids = explode(',',$temp);
				list($insql, $parms) = $DB->get_in_or_equal($courseids);
				$crs = "c.idnumber $insql";
			endif;

			if( !empty($params['studentid']) ):
				$stu = "usr.idnumber = ?";
				$parms[] = $params['studentid'];
			endif;
			
			if( $stu && $crs ):
				$sql = "SELECT c.idnumber AS courseid, DATE( FROM_UNIXTIME( la.timeaccess ) ) AS lastaccessdate
									FROM {user_lastaccess} la
									JOIN {course} c ON la.courseid = c.id
									JOIN {user} usr ON la.userid = usr.id
								 WHERE $crs 
								 			 AND $stu";	
				if( $sql ):

					if($records = $DB->get_records_sql($sql,$parms)):
						foreach($records as $record):
							$activity[strtolower($record->courseid)] = $record->lastaccessdate;
						endforeach;
					endif;

					if( $activity ):
						return json_encode($activity);
					endif;

				endif;
			endif;
    }
    // Returns description of method result value
    public static function get_student_activity_returns() {
        return new external_value(PARAM_TEXT, 'For a single student (specified by student ID number), returns date of last access for each course (specified in a csv list of course IDs) in a json array indexed by course ID number.');
    }

// GET GROUP ACTIVITY ///////////////////////////////////////////////////////////////////////////////////////////
// Date of last access for multiple students in one or more courses

    // Returns description of method parameters
    public static function get_group_activity_parameters() {
        return new external_function_parameters(
					array(
						'studentidscourseids' => new external_value(PARAM_TEXT, 'A list of student ID numbers (PEN) and course ID numbers. Data layout is studentid,courseid|studentid,courseid,courseid|studentid,courseid|etc.', VALUE_DEFAULT, '')
					)
        );
    }
    // Returns date of last access, indexed by PEN (or whatever is in the student ID number field)
    public static function get_group_activity($studentidscourseids) {
			global $DB;
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::get_group_activity_parameters(),
					array('studentidscourseids' => $studentidscourseids)
			);
			//echo "<pre>\$params = ".print_r($params,true)."</pre>";		
			if( !empty($params['studentidscourseids']) ):
				$students = explode('|',$params['studentidscourseids']);
				//echo "<pre>\$students = ".print_r($students,true)."</pre>";		
				foreach($students as $studentidcourseids):
					// make sure that the previous student's data doesn't carry forward
					unset($student,$parms);
					// get the divider between the studentid and the first courseid
					$firstcommapos = strpos($studentidcourseids,',');
					if( $firstcommapos>0 ):
						$studentid = substr($studentidcourseids,0,$firstcommapos);
						$courseids = substr($studentidcourseids,$firstcommapos+1);
						$temp = str_replace("'","",$courseids); // we don't want these wrapped in single quotes
						$arrCourseids = explode(',',$temp);
						
						if( $courseids ):
							// we can't pass a csv list as a parameter because it is seen as a single value with embedded commas
							// -> convert the csv list to an array
							// -> use get_in_or_equal() to create the list
							// -> original specification for this function was to pass singlequote-delimited courseids
							$temp = str_replace("'","",$courseids); // make sure the single quotes are gone
							$arrCourseids = explode(',',$temp);
							list($insql, $parms) = $DB->get_in_or_equal($arrCourseids);
							$crs = "c.idnumber $insql";
						endif;
			
						if( $studentid ):
							$stu = "usr.idnumber = ?";
							$parms[] = $studentid;
						endif;
					
						if( $stu && $crs ):

							// initialize each course with '0000-00-00'
							// -> if the student has never logged in then we won't get anything back from Moodle
							foreach($arrCourseids as $courseid):
								$student[$courseid] = '0000-00-00';
							endforeach;

							$sql = "SELECT c.idnumber AS courseid, DATE( FROM_UNIXTIME( la.timeaccess ) ) AS lastaccessdate 
												FROM {user_lastaccess} la 
												JOIN {course} c ON la.courseid = c.id 
												JOIN {user} usr ON la.userid = usr.id 
											 WHERE $crs 
											 			 AND $stu";	
							// get the dates
							if($records = $DB->get_records_sql($sql,$parms)):
								foreach($records as $record):
									$student[strtolower($record->courseid)] = $record->lastaccessdate;
								endforeach;
							endif;
							
							$activity[$studentid] = $student;
							
						endif;
					endif;
				endforeach;

				return json_encode($activity);
	
			endif;
    }
    // Returns description of method result value
    public static function get_group_activity_returns() {
        return new external_value(PARAM_TEXT, 'For a group of students (specified by student ID numbers), returns date of last access for each course (specified in a csv list of course IDs) in a 2-D json array indexed by studentid and then by course ID number. Expected input: studentid,\'courseid\'|studentid,\'courseid\',\'courseid\'|studentid,\'courseid\'|etc.');
    }

// GET GRADEBOOK ///////////////////////////////////////////////////////////////////////////////////////////
/*
IF...
 1) course specified, but not student --> return summary info for all students in specified course --> if allmarks=1, include all marks for all students
 2) student specified, but not course --> return summary info for all courses for specified student
 3) student AND course specified --> return summary info PLUS all gradebook details


*/

    // Returns description of method parameters
    public static function get_gradebook_parameters() {
        return new external_function_parameters(
					array(
						'courseids' => new external_value(PARAM_TEXT, 'Course ID number, single or csv list.', VALUE_DEFAULT, ''),
						'studentid' => new external_value(PARAM_TEXT, 'A student ID number (PEN). If not specified, report for entire class.', VALUE_DEFAULT, ''),
						'pctcomplete' => new external_value(PARAM_TEXT, 'Calculation method for determining percent complete. Either "count" or "weight"', VALUE_DEFAULT, '"count"'),
						'allmarks' => new external_value(PARAM_TEXT, 'Return all marks for all students', VALUE_DEFAULT, '0'),
						'nodetails' => new external_value(PARAM_TEXT, 'Return only summary info for a single student', VALUE_DEFAULT, '0'),
						'debug' => new external_value(PARAM_TEXT, 'Display extended debugging information', VALUE_DEFAULT, '0')
					)
        );
    }

    public static function get_gradebook($courseids,$studentid,$pctcomplete,$allmarks,$nodetails,$debug) {
			global $DB;
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::get_gradebook_parameters(),
					array('courseids' => $courseids,'studentid' => $studentid,'pctcomplete' => $pctcomplete,'allmarks' => $allmarks,'nodetails' => $nodetails,'debug' => $debug)
			);
			if( $debug ) $debugout = "<pre>\$params = ".print_r($params,true)."</pre>";		
			// initialize a few vars
			$debugout = '';
			$stu = '';
			$crs = '';
			self::$categoriesout = '';
			self::$itemsout = '';
			$nodetails = '';
			
			// process the courseids and studentid parameters
			if( $studentid ) $stu = "AND usr.idnumber = '$studentid'"; 
			if( $courseids ):
				if( is_array($courseids) ):
					$arrCourseids = $courseids;
					$crs = 'list';
				else:
					$arrCourseids[] = $courseids;
					$crs = 'single';
				endif;
			else:
				$arrCourseids[] = '';
			endif;

			$parms = array();
			$stuparms = array();
			$crsparms = array();
			
//			if( $courseids ):
//				// we can't pass a csv list as a parameter because it is seen as a single value with embedded commas
//				// -> convert the csv list to an array
//				// -> use get_in_or_equal() to create the list
//				// -> original specification for this function was to pass singlequote-delimited courseids
//				$temp = str_replace("'","",$courseids); // make sure the single quotes are gone
//				$arrCourseids = explode(',',$temp);
//				list($insql, $crsparms) = $DB->get_in_or_equal($arrCourseids);
//				$crs = "AND c.idnumber $insql";
//				$crsstuparms = $crsparms;
//			endif;

			if( $studentid ):
				$stu = "AND usr.idnumber = ?";
				$stuparms[] = $studentid;
	//			$crsstuparms[] = $studentid;
			endif;
			

			if ($debug && !$stu && !$crs):
				$debugout .= "Test output for Accounting 11<p>";
				$crs = "AND c.idnumber = ? "; // MAMP
				$stu = "AND usr.idnumber = ? "; // MAMP
				$stuparms[] = '123456782';
				$crsparms[] = 'mamp_test';
				$crsstuparms = array('mamp_test','123456782');
//				$crs = "AND c.idnumber='mamp_test'"; // MAMP
//				$stu = "AND usr.idnumber = '123456782'"; // MAMP
				//$crs = "AND c.idnumber='ac11_avs13'"; // AVS
				//$stu = "AND usr.idnumber = '116913823'"; // AVS
				//$crs = "c.idnumber='account11_s1_12'"; // EBUS awmath10_s1_12
				//$stu = "AND usr.idnumber = '117372847'"; // EBUS
			endif;
			
			if (!$stu && !$crs) die; // we don't want to scan the entire Moodle database!
			
			$getdetails = (($stu && $crs=='single' && !$nodetails) || $allmarks);
			$multicourse = ($stu && !$crs);

			foreach($arrCourseids as $courseid):

				unset($crsstuparms);
				
				if ($courseid): 
					// in case we're dealing with multiple students in multiple courses
					// -> if we're reporting all courses for a single student, $courseid will be ''			
					list($insql, $crsstuparms) = $DB->get_in_or_equal($courseid);
					$crs = "AND c.idnumber $insql";
				endif;
				
				// if not multicourse, get the gradebook categories and items here
				// If there are no students in Moodle, we would not get anything back unless we get the categories & items here
				if( !$multicourse && $courseid ):
					self::$categories = self::get_categories($crs,$courseid,$crsstuparms,$debug);
					$items = self::get_items($crs,$courseid,$crsstuparms,$debug);
				endif;
				
				if( $stu ):				
					$crsstuparms[] = $studentid;
				endif;
				
				$class = array();
				$students = array();
				$studentsql = "SELECT DISTINCT usr.username, usr.lastname, usr.firstname, usr.idnumber 
												 FROM {course} c 
									 			 JOIN {context} cx ON c.id = cx.instanceid AND cx.contextlevel = '50'
									 			 JOIN {role_assignments} ra ON cx.id = ra.contextid 
									 			 JOIN {role} r ON ra.roleid = r.id 
									 			 JOIN {user} usr ON ra.userid = usr.id 
									 			WHERE (r.name = 'Student' OR r.shortname = 'Student') AND (usr.idnumber+0) > 0 $crs $stu
										 ORDER BY usr.lastname, usr.firstname";
				if( $debug ) $debugout .= "<p>Query to get students:<br>$studentsql</p>\n";
				
				// get an array of users OR a single user if $stu is specified
				if ($debug):
					$debugout .= "<p>Students in this report:</p>\n";
					$debugout .= "<ol>\n";
				endif;

				$records = $DB->get_records_sql($studentsql,$crsstuparms);

				foreach( $records as $record ):

					$student = (array)$record;

					// build the demographics part of the line
					if( $debug ) $debugout .= "<li>$student[firstname] $student[lastname], idnumber=$student[idnumber], username=$student[username]\n";

					// get a list of courses (if getting all courses for single user), otherwise this will just return the course idnumber we already have
					if (!$courseid):
						$coursesql = "SELECT DISTINCT c.idnumber 
														FROM {course} c 
														JOIN {context} cx ON c.id = cx.instanceid AND cx.contextlevel = '50' 
														JOIN {role_assignments} ra ON cx.id = ra.contextid 
														JOIN {role} r ON ra.roleid = r.id 
														JOIN {user} usr ON ra.userid = usr.id 
													 WHERE (r.name = 'Student' OR r.shortname = 'Student') $crs $stu
													 ORDER BY c.fullname";
						$parms = $crsstuparms;
					else:
						$coursesql = "SELECT ? AS idnumber";
						unset($parms);
						$parms[] = $courseid;
					endif;
					//if( $debug ) $debugout .= "<br>\$coursesql = $coursesql\n";
								
					$records = $DB->get_records_sql($coursesql,$parms);
					foreach( $records as $record ):
						
						$course = (array)$record;
						
						$crs = "AND c.idnumber = ? "; // this is required if getting all courses for single user (redundant otherwise)
						unset($parms);
						$parms[] = $course['idnumber'];
						//if( $debug ) $debugout .= "<br>course ID number=$crsrecord->idnumber\n";

						if( !isset(self::$categories) )	self::$categories = self::get_categories($crs,$course['idnumber'],$parms,$debug);

						if (!isset($items)) $items = self::get_items($crs,$course['idnumber'],$parms,$debug);

						// get last access date
						array_unshift($parms, $student['idnumber']); // prepend the studentid for all further queries
						$accesssql = "SELECT DATE( FROM_UNIXTIME( la.timeaccess ) ) AS lastaccessdate
														FROM {user_lastaccess} la
														JOIN {course} c ON la.courseid = c.id 
														JOIN {user} usr ON la.userid = usr.id 
											     WHERE usr.idnumber = ? 
													 			 $crs";
						$records = $DB->get_records_sql($accesssql,$parms);
						foreach( $records as $record ):
							$course['lastaccessdate'] = $record->lastaccessdate;
						endforeach;
				
						// get the course total mark
						// --> adding 'TRIM' and '+0' will drop trailing zeroes!
						$totalsql = "SELECT ROUND(gg.finalgrade,1) AS finalgrade
													 FROM {grade_grades} gg 
										 			 JOIN {grade_items} gi ON gi.id = gg.itemid 
										 			 JOIN {course} c ON c.id = gi.courseid 
										 			 JOIN {user} usr ON gg.userid = usr.id 
										   		WHERE gi.itemtype = 'course' 
																AND usr.idnumber = ? 
																$crs";
						if( $debug ) $debugout .= "<p>query for coursetotal<br>$totalsql</p>";
						$records = $DB->get_records_sql($totalsql,$parms);
						foreach( $records as $record ):
							$course['coursetotal'] = $record->finalgrade;
						endforeach;
								
						// get this student's Moodle start date
						$course['moodlestartdate'] = '';
						$startsql = "SELECT ue.timestart, ue.timecreated
													 FROM {user_enrolments} ue 
										 			 JOIN {enrol} e ON e.id = ue.enrolid 
										 			 JOIN {course} c ON c.id = e.courseid 
										 			 JOIN {user} usr ON usr.id = ue.userid 
										 			WHERE usr.idnumber = ? 
																$crs";
						$records = $DB->get_records_sql($startsql,$parms);
						foreach( $records as $record ):
							if($record->timestart>0):
								$course['moodlestartdate'] = date("Y-m-d", $record->timestart);
							elseif($record->timecreated>0):
								$course['moodlestartdate'] = date("Y-m-d", $record->timecreated);
							endif;
						endforeach;
			
						// get this student's active date
						$course['activedate'] = '';
						if (self::$activation_trigger):
							$activesql = "SELECT gg.finalgrade, gg.timemodified
															FROM {grade_grades} gg 
															JOIN {grade_items} gi ON gi.id = gg.itemid 
															JOIN {course} c ON c.id = gi.courseid 
															JOIN {user} usr ON gg.userid = usr.id 
														 WHERE gi.itemname = 'activation_trigger' 
														 			 AND usr.idnumber = ? 
																	 $crs";
							$records = $DB->get_records_sql($activesql,$parms);
							foreach( $records as $record ):
								if ($record->finalgrade > 0):
									$course['activedate'] = date("Y-m-d", $record->timemodified);
								endif;
							endforeach;
						endif;

						// make a space for the WD request date
						$course['wdrequestdate'] = '0000-00-00';
								
						// always get the percent complete by itemcount
						// get a count of non-hidden and non-excluded items with a final grade
						$donesql = "SELECT COUNT(gg.finalgrade) AS itemsdone
													FROM {grade_grades} gg 
													JOIN {grade_items} gi ON gg.itemid=gi.id 
													JOIN {grade_categories} gc ON gc.id = gi.categoryid 
													JOIN {course} c ON c.id = gi.courseid 
													JOIN {user} usr ON gg.userid = usr.id 
												 WHERE usr.idnumber = ? 
												 			 AND (gi.itemtype = 'mod' or gi.itemtype = 'manual') 
															 AND gi.hidden <> '1' 
															 AND gg.finalgrade > '0' 
															 AND gg.excluded = '0' 
															 $crs";
//						 AND gc.depth > '1' <-- removed so we can include uncategorized items
						$course['itemsdone'] = 0; // in case query fails
						$records = $DB->get_records_sql($donesql,$parms);
						foreach( $records as $record ):
							$course['itemsdone'] = $record->itemsdone;
						endforeach;

						// get a count of excluded items for this student
						$excludedsql = "SELECT COUNT(gg.excluded) AS excludeditems
															FROM {grade_grades} gg 
															JOIN {grade_items} gi ON gg.itemid=gi.id 
															JOIN {grade_categories} gc ON gc.id = gi.categoryid 
															JOIN {course} c ON c.id = gi.courseid 
															JOIN {user} usr ON gg.userid = usr.id 
														 WHERE usr.idnumber = ? 
														 			 AND (gi.itemtype = 'mod' or gi.itemtype = 'manual') 
																	 AND gi.hidden <> '1' 
																	 AND gg.excluded > 0 
																	 $crs";
						$course['excludeditems'] = 0; // in case query fails
						$records = $DB->get_records_sql($excludedsql,$parms);
						foreach( $records as $record ):
							$course['excludeditems'] = $record->excludeditems;
						endforeach;
	
						$course['itemstotal'] = count($items) - $course['excludeditems'];
						$course['pctcompletebycount'] = round($course['itemsdone']/$course['itemstotal']*100,0);
						$course['pctcompletebyweight'] = ''; // so that it's not at the end of the list of marks
						$course['mostrecentmarkdate'] = ''; // so that it's not at the end of the list of marks
									
						if ($pctcomplete == 'weight' || $getdetails):							
							// set the totals to 0
							foreach(self::$categories as $catid => $catdetails):
								$studenttotals[$catid] = 0;
								$studentexcluded[$catid] = 0;
							endforeach;					
							// get the student's marks so we can determine percent complete by weight
							// --> adding 'TRIM' and '+0' will drop trailing zeroes!
							$markssql = "SELECT gg.itemid, gg.timemodified, TRIM(gg.finalgrade)+0 AS finalgrade, gg.excluded";
							if ($getdetails) $markssql .= ", gg.feedback"; // only get feedback if called for a single student
							$markssql .= " FROM {grade_grades} gg 
											 			 JOIN {grade_items} gi ON gi.id = gg.itemid 
											 			 JOIN {grade_categories} gc ON gc.id = gi.categoryid 
											 			 JOIN {course} c ON c.id = gi.courseid 
											 			 JOIN {user} usr ON gg.userid = usr.id 
											 			WHERE (gi.itemtype = 'mod' or gi.itemtype = 'manual')";
							if ($getdetails):
								// single student, must either have a finalgrade or feedback (only need this if called for a single student and a single course)
								$markssql .= " AND ((NOT gg.finalgrade IS NULL AND gg.finalgrade <> '') OR gg.feedback <> '' OR gg.excluded > 0)";
							else:
								// entire class, must have a finalgrade
								$markssql .= " AND ((NOT gg.finalgrade IS NULL AND gg.finalgrade <> '') OR gg.excluded > 0)";
							endif;
							$markssql .= " AND gi.hidden <> '1' 
														 AND usr.idnumber = ? 
														 $crs
										ORDER BY gi.sortorder";
		//					$markssql .= " AND gi.hidden <> '1' 
		//						 AND usr.idnumber = '$student[idnumber]' 
		//						 AND gc.depth > '1'
		//						 $crs 	
		//						ORDER BY gi.sortorder";
	//echo "<p>QUERY TO GET MARKS<br>$markssql</p>";

							if ($getdetails) $marks = array(); // only need this if called for a single student and a single course
							if ($debug)	$debugout .=  "<p>Student marks for $course[idnumber]<ol>\n";
							$mostrecentmarkdate = 0;
							$records = $DB->get_records_sql($markssql,$parms);
							foreach( $records as $record ):
								$marksrow = (array)$record;
								if ($debug):
									$debugout .=  "<li>itemid=$marksrow[itemid], finalgrade=$marksrow[finalgrade], grademax=".$items[$marksrow['itemid']]['grademax'].", itemname=".$items[$marksrow['itemid']]['itemname'].", timestamp=".date("Y-m-d", $marksrow['timemodified']).", excluded=$marksrow[excluded]";
									if ($getdetails):
										// only need this if called for a single student and a single course
										$feedback = strip_tags($marksrow['feedback']);
										$debugout .=  ", feedback=".(substr($feedback,0,60)).((strlen($feedback)>60) ? '...' : '' );
										$debugout .= "<br>$marksrow[feedback]";
									endif;
									$debugout .=  "</li>\n";
								endif;
								if ($getdetails):
									//$marksrow['feedback'] = strip_tags($marksrow['feedback']);
									$marks[$marksrow['itemid']] = $marksrow; // only need this if called for a single student and a single course
//$debugout .= "<br>".print_r($marksrow,true);
								endif;
								if( $marksrow['excluded'] > 0 ):
									$studentexcluded[$items[$marksrow['itemid']]['categoryid']] += $items[$marksrow['itemid']]['grademax'] * $items[$marksrow['itemid']]['itemweight'];
								elseif ($marksrow['finalgrade']):
									$studenttotals[$items[$marksrow['itemid']]['categoryid']] += $items[$marksrow['itemid']]['grademax'] * $items[$marksrow['itemid']]['itemweight'];
									if ($marksrow['timemodified']>$mostrecentmarkdate):
										$mostrecentmarkdate = $marksrow['timemodified'];
									endif;
								endif;
							endforeach; // next grade book item
							if ($mostrecentmarkdate) $course['mostrecentmarkdate'] = date("Y-m-d", $mostrecentmarkdate);
							if ($debug)	$debugout .=  "</ol>\n<p>\n";
							if ($getdetails):
								// only report item marks if called for a single student and a single course
								$course['marks'] = $marks;
							endif;

						else: // look for the date of the most recent mark
							$mrm_sql = "SELECT MAX(gg.timemodified) AS mrm_date 
														FROM {grade_grades} gg 
														JOIN {grade_items} gi ON gi.id = gg.itemid 
														JOIN {grade_categories} gc ON gc.id = gi.categoryid 
														JOIN {course} c ON c.id = gi.courseid 
														JOIN {user} usr ON gg.userid = usr.id 
													 WHERE (gi.itemtype = 'mod' or gi.itemtype = 'manual') 
													 			 AND gi.hidden <> '1' 
																 AND usr.idnumber = ? 
																 $crs";
							$records = $DB->get_records_sql($mrm_sql,$parms);
							foreach( $records as $record ):
								$course['mostrecentmarkdate'] = date("Y-m-d", $record->mrm_date);
							endforeach;
						
						endif;
						
						if ($pctcomplete == 'weight'):
							// calculate percent complete by weight
							$pctcompletebyweight = 0;
							foreach(self::$categories as $catid => $catdetails):
								$pctcompletebyweight += ($studenttotals[$catid] / ($catdetails['categorymax'] - $studentexcluded[$catid]) * $catdetails['categoryweight']);
								//$pctcompletebyweight += ($studenttotals[$catid] / (self::$categorytotals[$catid] - $studentexcluded[$catid]) * $catdetails['categoryweight']);
							endforeach;
							$course['pctcompletebyweight'] = round($pctcompletebyweight,0);
						endif;
			
						// summary for this course for this student
						if ($debug):
							$debugout .=  "<p>Summary for  $course[idnumber]\n";
							$debugout .=  "<ul>\n";
							$debugout .=  "<li>Course total... ".((isset($course['coursetotal'])) ? "$course[coursetotal]" : "N/A")."</li>\n";
							$debugout .=  "<li>Moodle start date... ".((isset($course['moodlestartdate'])) ? "$course[moodlestartdate]" : "N/A")."</li>\n";
							$debugout .=  "<li>Last access date... ".((isset($course['lastaccessdate'])) ? "$course[lastaccessdate]" : "N/A")."</li>\n";
							if (self::$activation_trigger):
								$debugout .=  "<li>Declared active on... ".(($course['activedate']) ? "$course[activedate]" : "N/A")."</li>\n";
							endif;
							$debugout .=  "<li>Date of most recent mark... ".(($course['mostrecentmarkdate']) ? "$course[mostrecentmarkdate]" : "N/A")."</li>\n";
							$debugout .=  "<li>Percent complete by count... $course[pctcompletebycount]% ($course[itemsdone] of $course[itemstotal])</li>";
							if ($pctcomplete == 'weight'):
								$debugout .=  "<li>Percent complete by weight... $course[pctcompletebyweight]%";
								if ($course['itemsdone']):
									$debugout .=  "<table border='1' cellpadding='2' style='border-collapse:collapse'>\n";
									foreach(self::$categories as $catid => $catdetails):
				//						$cattotal = round(($studenttotals[$catid] / $categorytotals[$catid] x $catdetails['categoryweight']),2);					
										//$cattotal = number_format((float)($studenttotals[$catid] / (self::$categorytotals[$catid] - $studentexcluded[$catid]) * $catdetails['categoryweight']),2);					
										//$debugout .=  "<tr><td>$catdetails[categoryname]</td><td align='center'>".(0+$studenttotals[$catid])." / ".(self::$categorytotals[$catid] - $studentexcluded[$catid]) * $catdetails['categoryweight']."</td><td align='right'>$cattotal</td></tr>\n";
										$cattotal = number_format((float)($studenttotals[$catid] / ($catdetails['categorymax'] - $studentexcluded[$catid]) * $catdetails['categoryweight']),2);					
										$debugout .=  "<tr><td>$catdetails[categoryname]</td><td align='center'>".(0+$studenttotals[$catid])." / ".($catdetails['categorymax'] - $studentexcluded[$catid])." * ".number_format((float)$catdetails['categoryweight'],2)."</td><td align='right'>$cattotal</td></tr>\n";
									endforeach;
									$debugout .=  "</table>\n";
									foreach(self::$categories as $catid => $catdetails):
										//$debugout .=  "($catdetails['categorymax'] - \$studentexcluded[$catid]) / ($catdetails['categorymax'] - \$studentexcluded[$catid]) * \$catdetails['categoryweight'] = (".self::$categorytotals[$catid]." - ".$studentexcluded[$catid].") / ("$catdetails['categorymax']." - ".$studentexcluded[$catid].") * ".$catdetails['categoryweight']." = ".(self::$categorytotals[$catid] - $studentexcluded[$catid]) / 100 * $catdetails['categoryweight']."\n<br>";
									endforeach;
								endif;
							endif;
							$debugout .=  "</ul>\n"; // end the bullets
						endif;
																			
						$courses[$course['idnumber']] = $course; // add the current course to the courses array
		
						unset($studenttotals); // in case we're getting an entire class, clear the previous student's totals
						unset($studentexcluded); 
						if ($multicourse):
							unset($items); // clear for the next course
							self::$categories = array();
						endif;

					endforeach; // next $crsrecord
					
					$student['courses'] = $courses;
					unset($courses);
						
					$students[$student['idnumber']] = $student;
				
					if ($debug) $debugout .= "<p>=========================================</p>\n</li>\n"; // mark the end of one student's data

				endforeach; // next $sturecord						

				if ($debug):
					$debugout .= "</ol>\n";
					$debugout .= self::$categoriesout;
					$debugout .= self::$itemsout;
					if (!count($students)):
						// no students! -> there was a problem, so show the query
						$debugout .= "<p>No students!<p>$studentsql</p>\n";
					endif;
					if( strpos($debug,'@') ):
						mail($debug,'externallib.php: get_gradebook',$debugout);
					elseif( $debug=='json' ):
						$reporter['debug'] = $debugout;
					else:
						echo $debugout;
					endif;
				endif;
				
				//if($debug) echo "<pre>".print_r($students,true)."</pre>";
				//if($debug) echo "About to add \$students to \$class['students']<p>";
				$class['students'] = $students;
				//if($debug) "<pre>".print_r($class,true)."</pre>";
				if ($getdetails):
					// request is for student AND course OR all marks for all students, so return the full gradebook details
					$gradebook['categories'] = self::$categories;
					$gradebook['items'] = $items;
					$class['gradebook'] = $gradebook;
					//if($debug) "<pre>".print_r($class,true)."</pre>";
				endif;		
				
				if (!$courseid) $courseid = 0;
				$reporter[$courseid] = $class;
				
				unset($items,$debugout,$students,$gradebook,$class);
				self::$categories = array();
				self::$categoriesout = '';
				self::$itemsout = '';

			endforeach; // next courseidlist
			
			return json_encode($reporter);
			
    }
		
		private static function get_categories($crs,$crsid,$crsparms,$debug){
			// get the gradebook categories (needed for calculating percent complete AND for section headings on the reports page) gi.aggregation as weightingtype, 
			
			global $DB;
			$categories = array();
			$weightingTypes = array();
			$weightingTypes[MEAN_OF_GRADES] = "MEAN_OF_GRADES";
			$weightingTypes[WEIGHTED_MEAN] = "WEIGHTED_MEAN";
			$weightingTypes[SIMPLE_WEIGHTED_MEAN] = "SIMPLE_WEIGHTED_MEAN";

			// This subquery won't work because there might be differently weighted items in a "weighted mean" category 
			//	,	( SELECT SUM(grademax) FROM mdl_grade_items WHERE categoryid = gi.iteminstance ) AS categorymax 
			
			$categorysql = "SELECT gc.fullname as categoryname, gi.iteminstance as categoryid, gc.aggregation as weightingtype, 
														 gi.aggregationcoef as categoryweight, gc.depth
												FROM {grade_items} gi
												JOIN {grade_categories} gc ON gc.id = gi.iteminstance 
												JOIN {course} c ON c.id = gi.courseid 
											 WHERE (gi.itemtype = 'category' OR gc.depth = 1) 
											 			 AND gi.hidden <> '1' 
														 $crs
											 ORDER BY gi.sortorder";
			if ($debug)	echo "<p>Query to get categories:\n<br>$categorysql<p>\n";
			if ($debug)	self::$categoriesout .=  "<p>Categories for $crsid:\n<ul>\n";
			$records = $DB->get_records_sql($categorysql,$crsparms);
			foreach( $records as $record ):
				$category = (array)$record;
				//if ($debug)	echo "<pre>".print_r($category,true)."</pre>";
				//if( !is_null($category['categorymax']) && $category['categorymax']>0):
					if ($category['categoryname']=='?'):
						$category['categoryname'] = 'Course';
						$rootid = $category['categoryid'];
					endif;
					$categories[$category['categoryid']] = $category;
					//self::$categorytotals[$category['categoryid']] = 0; // initialize for calculating pctcompletebyweight in the items loop && $category['categoryweight']>0
					if( $debug )	self::$categoriesout .=  "<li>categoryid=$category[categoryid], categoryname=$category[categoryname], categoryweight=$category[categoryweight], weightingtype=".$weightingTypes[$category['weightingtype']]."</li>\n";
				//endif;
			endforeach;
			//if ($debug)	self::$categoriesout .=  "</ul>\n"; //<pre>".print_r($categories,true)."</pre>\n";
			if( count($categories)==1 ):
				// no defined categories in this course, everything is in the root, so the courseid is the categoryid
				$categories[$rootid]['categoryweight'] = 100;
			elseif( $rootid ):
				unset($categories[$rootid]);
			endif;
			if ($debug):
				if (!count($categories)):
					self::$categoriesout .=  "<p>No categories!<p>$categorysql</p>\n";
				endif;
			endif;
			
			return $categories;
				
		}
		
		private static function get_items($crs,$crsid,$crsparms,$debug){
			// get the gradebook items
			
			global $DB;
			$items = array();
			$categorytotals = array();
			
			// get the gradebook items (needed for calculating percent complete)
			// -> adding 'TRIM' and '+0' will drop trailing zeroes!
			// -> all items will have an itemweight of 1 unless the teacher has set it differently in a "simple weighted" category
			$itemsql = "SELECT gi.id as itemid, gi.categoryid, gi.itemname, TRIM(gi.grademax)+0 AS grademax, gi.aggregationcoef as itemweight
										FROM {grade_items} gi
										JOIN {course} c ON c.id = gi.courseid 
										JOIN {grade_categories} gc ON gc.id = gi.categoryid 
									 WHERE (gi.itemtype = 'mod' or gi.itemtype = 'manual') 
									 			 AND gi.hidden <> '1' 
												 $crs 
									 ORDER BY itemid";
// 							 AND gc.depth > '1' <-- meant that uncategorized items were not reported!
			if ($debug)	self::$itemsout .=  "<p>Query to get item details:\n<br>$itemsql</p>\n";
			if ($debug)	self::$itemsout .=  "<p>Item details for $crsid:\n<ol>\n";
			$records = $DB->get_records_sql($itemsql,$crsparms);
			foreach( $records as $record ):
				$item = (array)$record;
				if( $debug) echo "item $item[itemid]: \$categories[$item[categoryid]]['weightingtype']=".self::$categories[$item['categoryid']]['weightingtype']."<br>";
				if( self::$categories[$item['categoryid']]['weightingtype']==SIMPLE_WEIGHTED_MEAN || self::$categories[$item['categoryid']]['weightingtype']==MEAN_OF_GRADES ) $item['itemweight']=1; // in case this was previously changed from WEIGHTED_MEAN
				$items[$item['itemid']] = $item;
				$categorytotals[$item['categoryid']] += $item['grademax']*$item['itemweight'];
				//if ($debug)	self::$itemsout .=  "<li>itemid=$item[itemid], categoryid=$item[categoryid], grademax=$item[grademax], itemname=$item[itemname], sortorder=$item[sortorder], category total (so far)=".self::$categorytotals[$item['categoryid']]."</li>\n";
				if ($debug)	self::$itemsout .=  "<li>itemid=$item[itemid], categoryid=$item[categoryid], grademax=$item[grademax], itemname=$item[itemname], itemweight=$item[itemweight], sortorder=$item[sortorder]</li>\n";
			endforeach;
			if ($debug)	self::$itemsout .=  "</ol>\n"; //<pre>".print_r($items,true)."</pre>\n";
			
			// add the category totals to the global categories array
			foreach($categorytotals as $categoryid=>$total):
				self::$categories[$categoryid]['categorymax'] = $total;
			endforeach;
			
			// see if this course has an activation_trigger assignment
			self::$activation_trigger = 0;
			$triggersql = "SELECT gi.* 
											 FROM {grade_items} gi
											 JOIN {course} c ON c.id = gi.courseid 
											WHERE gi.itemname = 'activation_trigger'
													  $crs";
			$records = $DB->get_records_sql($triggersql,$crsparms);
			foreach( $records as $record ):
				self::$activation_trigger = 1;
			endforeach;
			if ($debug)	self::$itemsout .=  "<p>This course ".((self::$activation_trigger) ? 'HAS' : 'DOES NOT HAVE' )." an activation_trigger</p>\n";													
					
//			// get rid of any categories that have no items
//			foreach(self::$categorytotals as $categoryid=>$total):
//				if ($total == 0):
//					unset(self::$categories[$categoryid]);
//				endif;
//			endforeach;

			if ($debug):
				if (!count($items)):
					// no items! -> there was a problem, so show the query
					self::$itemsout .=  "<p>No items!<p>$itemsql</p>\n";
				endif;
			endif;
			
			return $items;
		
		}
		
    // Returns description of method result value
    public static function get_gradebook_returns() {
        return new external_value(PARAM_RAW, 'Returns Moodle grade book as follows:<ol><li>course specified, but not student --> return summary info for all students in specified course</li>
									<li>student specified, but not course --> return summary info for all courses for specified student</li>
									<li>student AND course specified --> return summary info PLUS all gradebook details</li></ol>');
    }

// UPDATE EXCLUDED ///////////////////////////////////////////////////////////////////////////////////////////
// Set or clear "excluded" status of a gradebook item (or list of items)

    // Returns description of method parameters
    public static function update_excluded_parameters() {
        return new external_function_parameters(
					array(
						'courseid' => new external_value(PARAM_TEXT, 'A course ID number (SIM\'s courseid).', VALUE_DEFAULT, ''),
						'studentid' => new external_value(PARAM_TEXT, 'A student ID number (PEN).', VALUE_DEFAULT, ''),
						'itemids' => new external_value(PARAM_TEXT, 'Moodle\'s ID of a gradebook item (single value or csv list)', VALUE_DEFAULT, ''),
						'newvalue' => new external_value(PARAM_TEXT, 'New value for exluded status (0 or UNIX timestamp)', VALUE_DEFAULT, '')
					)
        );
    }
    // Sets exluded status of specified gradebook item for specified student
    public static function update_excluded($courseid, $studentid, $itemids, $newvalue) {
			
			global $DB;
			
			//Parameter validation -- REQUIRED
			$params = self::validate_parameters(self::update_excluded_parameters(),
					array('courseid' => $courseid, 'studentid' => $studentid, 'itemids' => $itemids, 'newvalue' => $newvalue)
			);
			//echo "<pre>\$params = ".print_r($params,true);
			
			// get user object from student ID
			$user = $DB->get_record('user', array('idnumber' => $studentid));
			//echo "<pre>\$user->id = $user->id";
			
			// get course object from course ID
			$course = $DB->get_record('course', array('idnumber' => $courseid));
			//echo "<pre>\$course->id = $course->id";
			
			$itemidsArray = explode(',',$itemids);
			//echo "<pre>\$itemidsArray = ".print_r($itemidsArray,true);

			foreach($itemidsArray as $itemid):
				// get grade_item object
				if (!$grade_item = grade_item::fetch(array('id'=>$itemid, 'courseid'=>$course->id))):
						print_error('cannotfindgradeitem');
				else:
					//echo "<pre>\$grade_item = ".print_r($grade_item,true)."</pre>";
		
					//$course_item = grade_item::fetch(array('courseid'=>$course->id, 'itemtype'=>'course'));
/*
			// get the course total before excluding the mark
			$totalsql = "SELECT ROUND(gg.finalgrade,1) AS finalgrade
FROM mdl_grade_grades AS gg 
INNER JOIN mdl_grade_items AS gi ON gi.id = gg.itemid 
INNER JOIN mdl_course AS c ON c.id = gi.courseid 
INNER JOIN mdl_user AS usr ON gg.userid = usr.id 
WHERE gi.itemtype = 'course'
AND usr.idnumber = '$studentid'
AND c.idnumber='$courseid'";
			$records = $DB->get_records_sql($totalsql);
			foreach( $records as $record ):
				echo "<pre>BEFORE<pre>coursetotal = $record->finalgrade";
			endforeach;
*/
					// get the grade_grade object for the selected item and user
					$grade_grade = new grade_grade(array('userid' => $user->id, 'itemid' => $itemid), true);
		
					//echo "<br>\$grade_grade->excluded = ".print_r($grade_grade->excluded,true);
					//echo "<br>state of \$grade_item->needsupdate: $grade_item->needsupdate";
					////echo "<br>state of \$course_item->needsupdate: $course_item->needsupdate";
		
					// update the excluded flag
					$grade_grade->set_excluded($newvalue);
					
					// force regrading of this item
					$grade_item->force_regrading();
					//$course_item->force_regrading();
		
					//echo "<pre>AFTER<pre>\$grade_grade->excluded = ".print_r($grade_grade->excluded,true);
					//echo "<br>state of \$grade_item->needsupdate: $grade_item->needsupdate";
					////echo "<br>state of \$course_item->needsupdate: $course_item->needsupdate";
					
/*
			// get the course total AFTER excluding the mark
			$totalsql = "SELECT ROUND(gg.finalgrade,1) AS finalgrade
FROM mdl_grade_grades AS gg 
INNER JOIN mdl_grade_items AS gi ON gi.id = gg.itemid 
INNER JOIN mdl_course AS c ON c.id = gi.courseid 
INNER JOIN mdl_user AS usr ON gg.userid = usr.id 
WHERE gi.itemtype = 'course'
AND usr.idnumber = '$studentid'
AND c.idnumber='$courseid'";
			//echo "<pre>about to report course total, \$totalsql = \n$totalsql</pre>";
			$records = $DB->get_records_sql($totalsql);
			foreach( $records as $record ):
				echo "<br>coursetotal = $record->finalgrade</pre>";
			endforeach;
*/			
				endif;
				
			endforeach;
			
			// force a gradebook regrade for this student
			$result = grade_regrade_final_grades($course->id); //, $user->id, $grade_item); //);
			//echo "<br>result of grade_regrade_final_grades(): ".print_r($result,true);			
			
			return $result;

    }
    // Returns description of method result value
    public static function update_excluded_returns() {
        return new external_value(PARAM_TEXT, 'Sets or clears exluded status for specified gradebook item for specified student. Returns 1 if successful.');
    }


}
