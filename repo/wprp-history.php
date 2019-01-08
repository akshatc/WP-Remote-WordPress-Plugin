<?php

class WPRP_History {

    public function get_info()
    {
        $info = WPRP_Update_Database::info();
        var_dump($info);
        exit;
    }
}