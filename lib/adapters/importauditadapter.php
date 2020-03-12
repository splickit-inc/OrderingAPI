<?php

class ImportAuditAdapter extends MySQLAdapter
{

    function ImportAuditAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'import_audit',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = false;

    }

    function auditTrail($sql)
    {
        return true;
    }


}
?>