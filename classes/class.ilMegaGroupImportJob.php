<?php

/**
 * Class ilMegaGroupImportJob
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class ilMegaGroupImportJob extends ilCronJob
{
    const CRON_IMPORT = ilMegaGroupPlugin::PLUGIN_ID . "_import";


    /**
     * @return string
     */
    public function getId()
    {
        return self::CRON_IMPORT;
    }
    public function getTitle()
    {
       return "Mega Import";
    }
    public function getDescription()
    {
        return "";
    }

    /**
     * @return bool
     */
    public function hasAutoActivation()
    {
       return true;
    }

    /**
     * @return bool
     */
    public function hasFlexibleSchedule()
    {
        return true;
    }

    /**
     * @return int
     */
    public function getDefaultScheduleType()
    {
        self::SCHEDULE_TYPE_DAILY;
    }

    /**
     * @return array|int
     */
    public function getDefaultScheduleValue()
    {
        return null;
    }

    /**
     * @return ilCronJobResult
     */
    public function run()
    {
        global $DIC;

        $result = new ilCronJobResult();

        $data = $this->parseData();
        $DIC->logger()->root()->dump($data);

        $this->buildData(array_slice($data, 0, 10));
        return $result;

    }
    public function parseData()
    {
        global $DIC;

        //1 .read from a file
        //2. read from ILIAS
        $file = "/var/local/ilias/megagroup/Test.csv";
        $reader = new ilCSVReader();
        $reader->setDelimiter(";");
        //$reader->setSeparator();
        $reader->open($file);
        $rows = $reader->getDataArrayFromCSVFile();
        $reader->close();
        $DIC->logger()->root()->dump($rows);
        $columns_map = [
            "Titel" => "title",
            "Beginn Veranstaltung" => "event_start",
            "Ende Veranstaltung" => "event_end",
            "Beginn Beitritt" => "registration_start",
            "Ende Beitritt" => "registration_end",
            "Spätester Gruppenaustritt" => "latest_unsubscribe"
        ];
        foreach ($rows as &$row) {
            $row = array_map(function (string $column): string {
                return $column;
            }, $row);
        }


        $head = array_shift($rows);

        $columns = array_map(function (string $column) use (&$columns_map, $DIC): string {
            if (substr($column, 0, 3) === "\xEF\xBB\xBF") {
                $column = substr($column, 3);
            }
            if (isset($columns_map[$column])) {
                return $columns_map[$column];
            } else {
                // Optimal column
                return "";
            }
        }, $head);
        foreach ($columns_map as $key => $value) {
            if (!in_array($value, $columns)) {
                // Column missing!
                throw new ilException("Column <b>$key ($value)</b> does not exists  in the file</b>!");
            }
        }


        // Get data
        $parse_data = [];
        $date_fields = array("event_start", "event_end", "event_start", "event_end", "latest_unsubscribe");
        foreach($rows as $rowId => $row_data) {
            unset($row);
            //$row = $rows[$i];
            $row = $row_data;
            if ($row === [0 => ""]) {
                continue; // Skip empty rows
            }
            $data = new stdClass();
            foreach ($row as $cellI => $cell) {
                $column_title = $columns[$cellI];
                if (!isset($column_title)) {
                    // Column missing!
                    throw new ilException("<b>Row $rowId, column $cellI</b> does not exists in <b>{}</b>!");
                }
                if ($column_title != "") { // Skip optimal columns
                    if (in_array($column_title, $date_fields)) {
                        $data->{$column_title} = (new DateTime($cell))->getTimestamp();
                    } else {
                        $data->{$column_title} = $cell;
                    }
                }
            }

            $parse_data [] = $data;
        }
        return $parse_data;


    }
    public function buildData($data)
    {
        foreach ($data as $course_item){
            if ($course_item->title){
                $group = new ilObjGroup();
                $group->setTitle($course_item->title);
                $group->enableUnlimitedRegistration(false);
                $group->setRegistrationStart(new ilDateTime($course_item->event_start, IL_CAL_UNIX));
                $group->setRegistrationEnd(new ilDateTime($course_item->event_end, IL_CAL_UNIX));
                $group->create();

                $group->createReference();
                $group->putInTree(1661);
                $group->setPermissions(1661);
            }

        }


    }
    public function checkValidity($course_data): bool
    {
        return false;
    }
    public function createExternalID($object)
    {

    }
    public function updateObject($object)
    {

    }
    public function createObject($object)
    {

    }


}