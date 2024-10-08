<?php
//include_once 'Customizing/global/plugins/Services/Notifications/classes/class.ilNotification.php';
/**
 * Class ilCourseCompletionNotifierImportJob
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class ilCourseCompletionNotifierImportJob extends ilCronJob
{
    const CRON_IMPORT = ilCourseCompletionNotifierPlugin::PLUGIN_ID . "_import";


    /**
     * @return string
     */
    public function getId() : string
    {
        return self::CRON_IMPORT;
    }
    public function getTitle() : string
    {
       return "CourseCompletionNotifier Import";
    }
    public function getDescription() : string
    {
        return "";
    }

    /**
     * @return bool
     */
    public function hasAutoActivation() : bool
    {
       return true;
    }

    /**
     * @return bool
     */
    public function hasFlexibleSchedule() : bool
    {
        return true;
    }

    /**
     * @return int
     */
    public function getDefaultScheduleType() : int
    {
        return self::SCHEDULE_TYPE_DAILY;
    }

    /**
     * @return array|int
     */
    public function getDefaultScheduleValue() : ?int 
    {
        return null;
    }

    /**
     * @return ilCronJobResult
     */
    public function run() : ilCronJobResult 
    {
        global $DIC;

        // Initialize result object
        $result = new ilCronJobResult();

        // Get database connection
        $db = $DIC->database();

        // User object to identify the sender
        $sender_id = $DIC->user()->getId();

        // Mail object to send internal messages
        $mail = new ilMail($sender_id);

        // Log the cron job start
        $DIC->logger()->root()->dump("CourseCompletionNotifier: Starting notification job");

        // SQL query to fetch students who completed the course
        $student_query = "SELECT u.usr_id, u.login, o.title AS course_title, o.obj_id 
                        FROM ut_lp_marks lp 
                        JOIN object_data o ON lp.obj_id = o.obj_id 
                        JOIN usr_data u ON lp.usr_id = u.usr_id 
                        WHERE lp.status = 2"; // 2 means "pass"

        $student_res = $db->query($student_query);
        
        $passed_students_by_course = [];  // To store passed students grouped by course

        // Loop through the result set for passed students
        while ($row = $db->fetchAssoc($student_res)) {
            $course_id = $row['obj_id'];
            $passed_students_by_course[$course_id]['title'] = $row['course_title'];
            $passed_students_by_course[$course_id]['students'][] = [
                'usr_id' => $row['usr_id'],
                'login' => $row['login']
            ];

            // Log each student who has passed
            $DIC->logger()->root()->dump("CourseCompletionNotifier: Passed student user_id: " . $row['usr_id'] . " in course: " . $row['course_title']);
        }

        // If no students passed, end the process early
        if (empty($passed_students_by_course)) {
            $DIC->logger()->root()->dump("CourseCompletionNotifier: No students have passed the courses.");
            $result->setStatus(ilCronJobResult::STATUS_OK);
            return $result;
        }

        // SQL query to fetch admins or tutors for each course
        $admin_query = "SELECT o.obj_id, o.title AS course_title, 
                        admin_usr.usr_id AS admin_id, admin_usr.login AS admin_login, 
                        CASE 
                            WHEN om.admin = 1 THEN 'Admin' 
                            WHEN om.tutor = 1 THEN 'Tutor' 
                        END AS role 
                        FROM object_data o 
                        JOIN obj_members om ON om.obj_id = o.obj_id 
                        AND (om.admin = 1 OR om.tutor = 1) 
                        JOIN usr_data admin_usr ON om.usr_id = admin_usr.usr_id";

        $admin_res = $db->query($admin_query);

        // Store passed students information by admin/tutor
        $passed_students_by_admin = [];

        // Loop through the result set for admins/tutors
        while ($row = $db->fetchAssoc($admin_res)) {
            $course_id = $row['obj_id'];
            $admin_id = $row['admin_id'];
            $admin_login = $row['admin_login'];
            $course_title = $row['course_title'];
            $role = $row['role'];

            // Check if this course has any passed students
            if (isset($passed_students_by_course[$course_id])) {
                $student_list = $passed_students_by_course[$course_id]['students'];

                // Prepare a summary for this course for the specific admin
                $course_summary = "\n" . $course_title . " has passed students:\n";
                foreach ($student_list as $student) {
                    $course_summary .= "- " . $student['login'] . " (User ID: " . $student['usr_id'] . ")\n";
                }

                // Group by admin/tutor for the specific course
                $passed_students_by_admin[$admin_id]['login'] = $admin_login;
                $passed_students_by_admin[$admin_id]['role'] = $role;
                $passed_students_by_admin[$admin_id]['courses'][] = $course_summary;

                // Log the grouping for the correct admin/tutor
                $DIC->logger()->root()->dump("CourseCompletionNotifier: Grouping course '$course_title' for $role (user_id: " . $admin_id . ")");
            }
        }

        // Now send a course-specific email to each admin/tutor
        foreach ($passed_students_by_admin as $admin_id => $admin_info) {
            $admin_login = $admin_info['login'];
            $role = $admin_info['role'];
            $courses_summary = implode("\n", $admin_info['courses']);

            // Log the sending process
            $DIC->logger()->root()->dump("CourseCompletionNotifier: Sending consolidated summary to " . $role . " (user_id: " . $admin_id . ")");

            // Send internal mail to admin/tutor for the courses they manage
            $mail->sendMail(
                $admin_login,                 // Recipient's login or user ID
                "",                           // CC (optional)
                "",                           // BCC (optional)
                "Course Completion Summary",   // Subject
                "Dear $role,\n\n" . 
                "Here is the list of students who have successfully passed the courses you are responsible for:\n\n" . 
                $courses_summary,             // Body
                [],                           // Attachments (optional)
                true                          // Send as internal message
            );

            // Log mail task creation
            $DIC->logger()->root()->dump("CourseCompletionNotifier: Consolidated summary mail sent to " . $role . " (user_id: " . $admin_id . ")");
        }

        // Log the cron job end
        $DIC->logger()->root()->dump("CourseCompletionNotifier: Finished notification job");

        $result->setStatus(ilCronJobResult::STATUS_OK);
        return $result;
    }
 

}