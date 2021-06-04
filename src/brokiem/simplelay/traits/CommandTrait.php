<?php

namespace brokiem\simplelay\traits;

use brokiem\simplelay\SimpleLay;
use pocketmine\plugin\Plugin;

trait CommandTrait {

    /** @return SimpleLay */
    public function getPlugin(): Plugin {
        return SimpleLay::getInstance();
    }
}