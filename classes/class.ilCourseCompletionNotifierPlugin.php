<?php
include_once "Customizing/global/plugins/Services/Cron/CronHook/CourseCompletionNotifier/classes/class.ilCourseCompletionNotifierImportJob.php";
class ilCourseCompletionNotifierPlugin extends  ilCronHookPlugin
{
    const PLUGIN_NAME = "CourseCompletionNotifier";
    const PLUGIN_ID = "ccnotifier";

    /**
     * @return mixed
     */
    public function getCronJobInstances() : array
    {
        return array(
            new ilCourseCompletionNotifierImportJob()
        );
    }

    /**
     * @param $a_job_id
     * @return mixed
     */
    public function getCronJobInstance(string $jobId): ilCronJob
    {
        return new ilCourseCompletionNotifierImportJob();
    }

    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }
}