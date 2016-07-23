<?php

/**
 * SIM Web Services local plugin external functions and service definitions.
 */

// We defined the web service functions to install.
$functions = array(
        'local_wssim_hello_world' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'hello_world',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'Validates SIM Web Services installation. Go to SIM/admin, <i>School Settings</i> tab, <i>Learning Management System</i> page, and click "Test Moodle Link".',
                'type'        => 'read',
        ),
        'local_wssim_get_course_names' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_course_names',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'Returns a json array of all courses (names and ids) in Moodle.',
                'type'        => 'read',
        ),
        'local_wssim_get_course_teachers' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_course_teachers',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'For a specified course (by short name or by ID number), returns a json array of teachers and editing teachers.',
                'type'        => 'read',
        ),
        'local_wssim_get_course_activity' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_course_activity',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'For all students in a specified course (by short name or by ID number), returns date of last access in a json array indexed by student ID number (e.g. PEN). Returned data can be limited to specific student(s) by specifying a csv list of student ID numbers, and omitting a course specification returns date of last access in any course for the specified student(s).',
                'type'        => 'read',
        ),
        'local_wssim_get_student_activity' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_student_activity',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'For a single student (specified by student ID number), returns date of last access for each course (specified in a csv list of course IDs) in a json array indexed by course ID number.',
                'type'        => 'read',
        ),
        'local_wssim_get_group_activity' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_group_activity',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'For a group of students (specified by student ID number), returns date of last access for each course (specified in a csv list of course IDs) in a 2-D json array indexed by student ID and then by course ID number.',
                'type'        => 'read',
        ),
        'local_wssim_get_gradebook' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'get_gradebook',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'Returns Moodle grade book as follows:<ol><li>course specified, but not student --> return summary info for all students in specified course</li>
									<li>student specified, but not course --> return summary info for all courses for specified student</li>
									<li>student AND course specified --> return summary info PLUS all gradebook details</li></ol>',
                'type'        => 'read',
        ),
        'local_wssim_update_excluded' => array(
                'classname'   => 'local_wssim_external',
                'methodname'  => 'update_excluded',
                'classpath'   => 'local/wssim/externallib.php',
                'description' => 'Sets the "excluded" state of a gradebook item',
                'type'        => 'read',
        )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
        'SIM Web Services' => array(
                'functions' => array (
								    'local_wssim_hello_world',
										'local_wssim_get_course_names',
										'local_wssim_get_course_teachers',
										'local_wssim_get_course_activity',
										'local_wssim_get_student_activity',
										'local_wssim_get_group_activity',
										'local_wssim_get_gradebook',
										'local_wssim_update_excluded'
									),
                'restrictedusers' => 0,
                'enabled'=>1,
        )
);
