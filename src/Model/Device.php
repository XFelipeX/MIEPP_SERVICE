<?php

class Device
{

  function __construct($id, $shop_id, $dept_id, $description, $imei)
  {
    $this->id = $id;
    $this->shop_id = $shop_id;
    $this->dept_id = $dept_id;
    $this->description = $description;
    $this->imei = $imei;
  }
}
