<#1>
<?php
global $DIC;
if(!$DIC->database()->tableExists('crn_megagroup')){
    $fields = array(
        'id' => array(
            'type' => 'text',
            'length' => 256,
            'notnull' => true
        ),
        'ilias_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => false
        ),
        'date_delivered' => array(
            'type' => 'timestamp',
            'notnull' => false

        ),
        'last_updated' => array(
            'type' => 'date',
            'notnull' => false

        )

    );
    $DIC->database()->createTable('crn_megagroup', $fields);
    $DIC->database()->addPrimaryKey('crn_megagroup', array('id'));

}
?>
<#1>
<?php
//some setp
?>


