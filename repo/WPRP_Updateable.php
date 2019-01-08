<?php

class WPRP_Updateable {
    protected $update_type;
    protected $update_id;
    protected $filename;
    protected $old_version = '';
    protected $new_version = '';


    public function markComplete($errors = false)
    {

    }
}