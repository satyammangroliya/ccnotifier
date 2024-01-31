<?php
include_once "Customizing/global/plugins/Services/Cron/CronHook/MegaGroup/classes/class.ilMegaGroupImportJob.php";
class ilMegaGroupPlugin extends  ilCronHookPlugin
{
    const PLUGIN_NAME = "MegaGroup";
    const PLUGIN_ID = "megagroup";

    /**
     * @return mixed
     */
    public function getCronJobInstances()
    {
        return array(
            new ilMegaGroupImportJob()
        );
    }

    /**
     * @param $a_job_id
     * @return mixed
     */
    public function getCronJobInstance($a_job_id)
    {
        return new ilMegaGroupImportJob();
    }

    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }
}