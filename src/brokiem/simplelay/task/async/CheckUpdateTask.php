<?php

declare(strict_types=1);

/*
 *  ____    _                       _          _
 * / ___|  (_)  _ __ ___    _ __   | |   ___  | |       __ _   _   _
 * \___ \  | | | '_ ` _ \  | '_ \  | |  / _ \ | |      / _` | | | | |
 *  ___) | | | | | | | | | | |_) | | | |  __/ | |___  | (_| | | |_| |
 * |____/  |_| |_| |_| |_| | .__/  |_|  \___| |_____|  \__,_|  \__, |
 *                         |_|                                 |___/
 *
 * Copyright (C) 2020 - 2021 brokiem
 *
 * This software is distributed under "GNU General Public License v3.0".
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License v3.0
 * along with this program. If not, see
 * <https://opensource.org/licenses/GPL-3.0>.
 *
 */

namespace brokiem\simplelay\task\async;

use brokiem\simplelay\SimpleLay;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class CheckUpdateTask extends AsyncTask {

    private const POGGIT_URL = "https://poggit.pmmp.io/releases.json?name=";

    private $version;
    private $name;

    public function __construct(SimpleLay $plugin) {
        $this->storeLocal([$plugin]);
        $this->name = $plugin->getDescription()->getName();
        $this->version = $plugin->getDescription()->getVersion();
    }

    public function onRun(): void {
        $poggitData = Internet::getURL(self::POGGIT_URL . $this->name);

        if (!$poggitData) {
            return;
        }

        $poggit = json_decode($poggitData, true);

        if (!is_array($poggit)) {
            return;
        }

        $version = "";
        $date = "";
        $updateUrl = "";

        foreach ($poggit as $pog) {
            if (version_compare($this->version, str_replace("-beta", "", $pog["version"]), ">=")) {
                continue;
            }

            $version = $pog["version"];
            $date = $pog["last_state_change_date"];
            $updateUrl = $pog["html_url"];
        }

        $this->setResult([$version, $date, $updateUrl]);
    }

    public function onCompletion(Server $server): void {
        /** @var SimpleLay $plugin */
        [$plugin] = $this->fetchLocal();

        if ($this->getResult() === null) {
            $plugin->getLogger()->debug("Async update check failed!");
            return;
        }

        [$latestVersion, $updateDateUnix, $updateUrl] = $this->getResult();

        if ($latestVersion != "" || $updateDateUnix != null || $updateUrl !== "") {
            $updateDate = date("j F Y", (int)$updateDateUnix);

            if ($this->version !== $latestVersion) {
                $plugin->getLogger()->notice("SimpleLay v$latestVersion has been released on $updateDate. Download the new update at $updateUrl");
                $plugin->cachedUpdate = [$latestVersion, $updateDate, $updateUrl];
            }
        }
    }
}