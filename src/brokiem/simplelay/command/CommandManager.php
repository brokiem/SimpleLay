<?php
declare(strict_types=1);

namespace brokiem\simplelay\command;

use pocketmine\Server;

class CommandManager {

    public static function init(Server $server): void {
        $server->getCommandMap()->registerAll("simplelay", [
            new LayCommand("lay"),
            new SimpleLayCommand("simplelay"),
            new SitCommand("sit"),
            new SitToggleCommand("sittoggle"),
            new SKickCommand("skick")
        ]);
    }
}