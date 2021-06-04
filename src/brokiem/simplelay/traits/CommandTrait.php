<?php

namespace brokiem\simplelay\traits;

use brokiem\simplelay\SimpleLay;
use pocketmine\plugin\Plugin;

trait CommandTrait {

    public function getPlugin(): Plugin {
        return SimpleLay::getInstance();
    }
}