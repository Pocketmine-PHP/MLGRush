<?php

namespace PrinxIsLeqit\MLGRush;

use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\level\sound\GhastSound;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\scheduler\PluginTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\Transferpacket;

class MLGRush extends PluginBase implements Listener {
    public $cfg;
    public $prefix = '§1M§fL§4G§fRush §8| §7';
    public $file;
    public $mode = 0;
    public $player = '';
    public $map;
    public $game = "MLGRush";

    public function onEnable() {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . '/games');
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->initConfig();
        }
        $this->cfg = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new MLGTask($this), 20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getDefaultLevel()->setTime(0);
        $this->getServer()->getDefaultLevel()->stopTime();

        $this->getLogger()->info($this->prefix . TextFormat::WHITE . ' enabled by PrinxIsLeqit!');
    }

    public function initConfig() {
        $this->cfg = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->cfg->set('text', FALSE);
        $this->cfg->set('x', 0);
        $this->cfg->set('y', 0);
        $this->cfg->set('z', 0);
        $this->cfg->set('world', 'world');
        $this->cfg->save();
    }

    public function onDisable() {
        $dir = $this->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $gamename . '.yml', Config::YAML);
            $blocks = $arenafile->get('blocks');
            foreach ($blocks as $block) {
                $b = explode(':', $block);
                $this->getServer()->getLevelByName($gamename)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
            }
            $arenafile->set('blocks', array());
            $arenafile->set('mode', 'waiting');
            $arenafile->set('counter', 0);
            $arenafile->set('playercount', 0);
            $arenafile->set('playerone', NULL);
            $arenafile->set('playertwo', NULL);
            $arenafile->set('winner1', NULL);
            $arenafile->set('winner2', NULL);
            $arenafile->set('winner3', NULL);
            $arenafile->save();

            $tiles = $this->getServer()->getDefaultLevel()->getTiles();
            foreach ($tiles as $tile) {
                if ($tile instanceof Sign) {
                    $text = $tile->getText();
                    if ($text['0'] == "§1M§fL§4G§fRush") {
                        if (TextFormat::clean($text['1']) == $gamename) {
                            $tile->setText(
                                "§1M§fL§4G§fRush",
                                $text['1'],
                                TextFormat::YELLOW . '0/2',
                                TextFormat::GREEN . 'JOIN'
                            );
                        }
                    }
                }
            }
        }
        $this->getLogger()->info($this->prefix . TextFormat::RED . 'Resetted all arenas!');
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $event->setJoinMessage("");
        $player->setImmobile(false);
        $player->setAllowMovementCheats(true);

        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            $p->sendPopup(TextFormat::GRAY . "[" . TextFormat::GREEN . "+" . TextFormat::GRAY . "] " . $event->getPlayer()->getName());
        }

        $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
		
		$player->setGamemode(1);
		$player->setGamemode(0);
    }

    public function onQuit(PlayerQuitEvent $event) {
        $event->setQuitMessage("");
        $player = $event->getPlayer();
        $this->getServer()->dispatchCommand($player, ("leave"));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $tiles = $this->getServer()->getDefaultLevel()->getTiles();
        if ($command == "mlgrush") {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (empty($args[0])) {
                $sender->sendMessage($this->prefix);
                $sender->sendMessage('/mlgrush create <map>');
                return FALSE;
            }
            if ($args[0] == 'create') {
                if (!$this->getServer()->getLevelByName($args['1'])) {
                    $sender->sendMessage('/mlgrush create <map>');
                    return FALSE;
                }
                $this->mode = 1;
                $this->player = $sender->getName();
                $this->map = $args[1];
                $this->file = new Config($this->getDataFolder() . '/games/' . $args[1] . '.yml', Config::YAML);
                $sender->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn());
                $sender->setGamemode(1);
                $sender->sendMessage('Please check console for error messages. Only continue if there are no!');
                $this->file->set('mode', 0);
                $this->file->set('playercount', 0);
                $this->file->set('counter', 0);
                $this->file->set('blocks', array());
                $this->file->set('winner1', NULL);
                $this->file->set('winner2', NULL);
                $this->file->set('winner3', NULL);
                $this->file->save();
                $sender->sendMessage($this->prefix . 'Please touch the spawn of the blue player!');
                return TRUE;
            }
        }
        if ($command == 'leave') {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (!$this->isPlaying($sender)) {
                $sender->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                return TRUE;
            } else {
                $arenaname = $this->getArena($sender);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $arenafile->save();
                    $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                    $sender->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    foreach ($tiles as $tile) {
                        if ($tile instanceof Sign) {
                            $text = $tile->getText();
                            if ($text['0'] == "§1M§fL§4G§fRush") {
                                if (TextFormat::clean($text['1']) == $arenaname) {
                                    $tile->setText(
                                        "§1M§fL§4G§fRush",
                                        $text['1'],
                                        TextFormat::YELLOW . '0/2',
                                        TextFormat::GREEN . 'JOIN'
                                    );
                                }
                            }
                        }
                    }
                }
                if ($mode == "ingame1" || $mode == "ingame2" || $mode == "ingame3" || $mode == "starting1" || $mode == "starting2" || $mode == "starting3") {
                    $playersina = $this->getServer()->getLevelByName($arenaname)->getPlayers();
                    foreach ($playersina as $p) {
                        $p->getInventory()->clearAll();
                        $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    }
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getServer()->getLevelByName($arenaname)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                    }
                    $arenafile->save();
                    foreach ($tiles as $tile) {
                        if ($tile instanceof Sign) {
                            $text = $tile->getText();
                            if ($text['0'] == "§1M§fL§4G§fRush") {
                                if (TextFormat::clean($text['1']) == $arenaname) {
                                    $tile->setText(
                                        "§1M§fL§4G§fRush",
                                        $text['1'],
                                        TextFormat::YELLOW . '0/2',
                                        TextFormat::GREEN . 'JOIN'
                                    );
                                }
                            }
                        }
                    }
                    $sender->setImmobile(false);
                }
                $sender->setImmobile(false);
            }
            return FALSE;
        }
        if ($command == 'hub') {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (!$this->isPlaying($sender)) {
                $sender->transfer('atomicmc.tk', 19132);
                return TRUE;
            } else {
                $arenaname = $this->getArena($sender);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getServer()->getLevelByName($arenaname)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                    }
                    $arenafile->save();
                    $tiles = $this->getServer()->getDefaultLevel()->getTiles();
                    $sender->transfer('127.0.0.1', 19133);
                    foreach ($tiles as $tile) {
                        if ($tile instanceof Sign) {
                            $text = $tile->getText();
                            if ($text['0'] == "§1M§fL§4G§fRush") {
                                if (TextFormat::clean($text['1']) == $arenaname) {
                                    $tile->setText(
                                        "§1M§fL§4G§fRush",
                                        $text['1'],
                                        TextFormat::YELLOW . '0/2',
                                        TextFormat::GREEN . 'JOIN'
                                    );
                                }
                            }
                        }
                    }
                }
                if ($mode == "ingame1" || $mode == "ingame2" || $mode == "ingame3" || $mode == "starting1" || $mode == "starting2" || $mode == "starting3") {
                    $playersina = $this->getServer()->getLevelByName($arenaname)->getPlayers();
                    foreach ($playersina as $p) {
                        $p->getInventory()->clearAll();
                        $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                        $sender->transfer('atomicmc.tk', 19132);
                    }
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $arenafile->save();
                    foreach ($tiles as $tile) {
                        if ($tile instanceof Sign) {
                            $text = $tile->getText();
                            if ($text['0'] == "§1M§fL§4G§fRush") {
                                if (TextFormat::clean($text['1']) == $arenaname) {
                                    $tile->setText(
                                        "§1M§fL§4G§fRush",
                                        $text['1'],
                                        TextFormat::YELLOW . '0/2',
                                        TextFormat::GREEN . 'JOIN'
                                    );
                                }
                            }
                        }
                    }
                }
            }
            return FALSE;
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $block = $event->getBlock();
        $blockid = $block->getId();
        $tile = $block->getLevel()->getTile($block);
        if ($playername == $this->player) {
            if ($this->mode == 1) {
                if ($blockid == 0) {
                    return;
                }
                $x = $block->x;
                $y = $block->y + 1;
                $z = $block->z;

                $this->file->set('player1x', $x);
                $this->file->set('player1y', $y);
                $this->file->set('player1z', $z);
                $this->file->save();

                $this->mode = 2;
                $player->sendMessage($this->prefix . 'Please touch the spawn of the red player!');
            } elseif ($this->mode == 2) {
                if ($blockid == 0) {
                    return;
                }
                $x = $block->x;
                $y = $block->y + 1;
                $z = $block->z;

                $this->file->set('player2x', $x);
                $this->file->set('player2y', $y);
                $this->file->set('player2z', $z);
                $this->file->save();

                $this->mode = 3;
                $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $player->sendMessage($this->prefix . 'Please touch the sign of this arena!');
            } elseif ($this->mode == 3) {
                if ($tile instanceof Sign) {
                    $tile->setText(
                        "§1M§fL§4G§fRush",
                        $this->map,
                        TextFormat::YELLOW . '0/2',
                        TextFormat::GREEN . 'JOIN'
                    );
                    $player->sendMessage($this->prefix . TextFormat::GREEN . 'Arena created!');
                    $this->file->set('mode', 'waiting');
                    $this->file->save();
                    $this->mode = 0;
                    $this->player = NULL;
                    $this->file = NULL;
                }
            }
            return;
        }
        if ($tile instanceof Sign) {
            $text = $tile->getText();
            if ($text['0'] == "§1M§fL§4G§fRush") {
                $arenaname = TextFormat::clean($text['1']);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $playercount = $arenafile->get('playercount');
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    if ($playercount == 0) {
                        $x = $arenafile->get('player1x');
                        $y = $arenafile->get('player1y');
                        $z = $arenafile->get('player1z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($arenaname)));
                        $player->getInventory()->clearAll();
                        $arenafile->set('playercount', 1);
                        $arenafile->set('playerone', $player->getName());
                        $arenafile->save();
                        $tile->setText(
                            "§1M§fL§4G§fRush",
                            $text['1'],
                            TextFormat::YELLOW . '1/2',
                            TextFormat::GREEN . 'JOIN'
                        );
                        $player->setImmobile(true);
                    } elseif ($playercount == 1) {
                        $x = $arenafile->get('player2x');
                        $y = $arenafile->get('player2y');
                        $z = $arenafile->get('player2z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getLevelByName($arenaname)));
                        $player->getInventory()->clearAll();
                        $arenafile->set('playercount', 2);
                        $arenafile->set('mode', 'starting1');
                        $arenafile->set('playertwo', $player->getName());
                        $arenafile->set('counter', 5);
                        $arenafile->save();
                        $tile->setText(
                            "§1M§fL§4G§fRush",
                            $text['1'],
                            TextFormat::YELLOW . '2/2',
                            TextFormat::RED . 'INGAME'
                        );
                        $player->setImmobile(true);
                    }
                    return;
                } else {
                    $player->sendMessage($this->prefix . TextFormat::RED . 'This arena is ingame!');
                }

                return;
            }
        }
    }


    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $playername = $event->getPlayer()->getName();
        if ($player->getLevel() == $this->getServer()->getDefaultLevel()) {
            $py = $player->y;
            if ($py < 10) {
                $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            }
        }
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode === 'waiting' or $mode === 'starting1' or $mode === 'starting2' or $mode === 'starting3') {

            } else {
                $py = $player->y;
                if ($py < 60) {
                    $player1 = $arenafile->get('playerone');
                    $player2 = $arenafile->get('playertwo');
                    if ($playername === $player1) {
                        $x = $arenafile->get('player1x');
                        $y = $arenafile->get('player1y');
                        $z = $arenafile->get('player1z');

                        $player->teleport(new Vector3($x, $y, $z));
                    } elseif ($playername === $player2) {
                        $x = $arenafile->get('player2x');
                        $y = $arenafile->get('player2y');
                        $z = $arenafile->get('player2z');

                        $player->teleport(new Vector3($x, $y, $z));
                    }
                }
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if (!$this->isPlaying($player)) {
            if (!$player->isOp()) {
                $event->setCancelled(true);
            }
        }
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode === 'ingame1' or $mode === 'ingame2' or $mode === 'ingame3') {
                if($arenafile->get("player1y") - 2 < $block->getY()){
                    $event->setCancelled(true);
                    return;
                }
                $x = $block->x;
                $y = $block->y;
                $z = $block->z;
                $blocks = $arenafile->get('blocks');
                $blocks[] = $x . ':' . $y . ':' . $z;
                $arenafile->set('blocks', $blocks);
                $arenafile->save();
            } else {
                $event->setCancelled(TRUE);
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $blockid = $block->getId();
        $playername = $player->getName();
        if (!$this->isPlaying($player)) {
            if(!$player->isOp()){
                $event->setCancelled(true);
            }
        }
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode == 'waiting' or $mode == 'starting1' or $mode == 'starting2' or $mode == 'starting3') {
                $event->setCancelled(TRUE);
                return;
            }
            if ($blockid == 35) {
                $x1 = $arenafile->get('player1x');
                $y1 = $arenafile->get('player1y');
                $z1 = $arenafile->get('player1z');

                $x2 = $arenafile->get('player2x');
                $y2 = $arenafile->get('player2y');
                $z2 = $arenafile->get('player2z');

                $player1spawn = new Vector3($x1, $y1, $z1);
                $player2spawn = new Vector3($x2, $y2, $z2);
                $distance1 = $player1spawn->distanceSquared($player);
                $distance2 = $player2spawn->distanceSquared($player);
                if ($distance1 > $distance2) {
                    if ($playername == $arenafile->get('playertwo')) {
                        $player->sendMessage($this->prefix . TextFormat::RED . "You cannot destroy your own woolblock!");
                        $event->setCancelled(TRUE);
                    } else {
                        $blocks = $arenafile->get('blocks');
                        $players = $this->getServer()->getLevelByName($arenaname)->getPlayers();
                        $this->getServer()->getPlayer($arenafile->get('playerone'))->getLevel()->addSound(new GhastShootSound($this->getServer()->getPlayer($arenafile->get('playerone'))));
                        $this->getServer()->getPlayer($arenafile->get('playertwo'))->getLevel()->addSound(new GhastShootSound($this->getServer()->getPlayer($arenafile->get('playertwo'))));
                        foreach ($players as $p) {
                            if ($p instanceof Player) {
                                $p->sendMessage($this->prefix . "The woolblock of " . TextFormat::DARK_RED . "Red" . TextFormat::GRAY . " was destroyed!");
                                $p->getLevel()->addSound(new GhastSound($p));
                                $p->setImmobile(true);
                            }
                        }
                        foreach ($blocks as $block) {
                            $b = explode(':', $block);
                            $this->getServer()->getLevelByName($arenaname)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                        }
                        if ($mode == 'ingame1') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting2');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame2') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting3');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame3') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner3', $player->getName());
                            $arenafile->set('counter', 0);
                            $arenafile->set('blocks', array());
                        }
                        $arenafile->save();
                        $event->setCancelled(TRUE);
                    }
                } elseif ($distance1 < $distance2) {
                    if ($playername == $arenafile->get('playerone')) {
                        $player->sendMessage($this->prefix . TextFormat::RED . "You cannot destroy your own woolblock!");
                        $event->setCancelled(TRUE);
                    } else {
                        $blocks = $arenafile->get('blocks');
                        $players = $this->getServer()->getLevelByName($arenaname)->getPlayers();
                        $this->getServer()->getPlayer($arenafile->get('playerone'))->getLevel()->addSound(new GhastShootSound($this->getServer()->getPlayer($arenafile->get('playerone'))));
                        $this->getServer()->getPlayer($arenafile->get('playertwo'))->getLevel()->addSound(new GhastShootSound($this->getServer()->getPlayer($arenafile->get('playertwo'))));
                        foreach ($players as $p) {
                            if ($p instanceof Player) {
                                $p->sendMessage($this->prefix . "The woolblock of " . TextFormat::DARK_BLUE . "Blue" . TextFormat::GRAY . " was destroyed!");
                                $p->getLevel()->addSound(new GhastSound($p));
                            }
                        }
                        foreach ($blocks as $block) {
                            $b = explode(':', $block);
                            $this->getServer()->getLevelByName($arenaname)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                        }
                        if ($mode == 'ingame1') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting2');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame2') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting3');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame3') {
                            $this->getServer()->getPlayer($arenafile->get('playerone'))->teleport($player1spawn);
                            $this->getServer()->getPlayer($arenafile->get('playertwo'))->teleport($player2spawn);
                            $arenafile->set('winner3', $player->getName());
                            $arenafile->set('counter', 0);
                            $arenafile->set('blocks', array());
                        }
                        $arenafile->save();
                        $event->setCancelled(TRUE);
                    }
                }
            }
            if ($blockid == 24) {
            } else {
                $event->setCancelled(TRUE);
            }
        }
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        if ($event->getCause() == EntityDamageEvent::CAUSE_FALL) {
            $event->setCancelled(TRUE);
        } elseif ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();
            if ($damager instanceof Player && $entity instanceof Player) {
                if (!$this->isPlaying($damager)) {
                    $event->setCancelled(TRUE);
                    return;
                }
                if (!$this->isPlaying($entity)) {
                    $event->setCancelled(TRUE);
                    return;
                }
                $damagerinv = $damager->getInventory();
                $iteminhand = $damagerinv->getItemInHand()->getId();
                if ($iteminhand == 280) {
                    $event->setKnockBack(0.5); //0.6
                    $event->setDamage(0);
                }
            }
        }
        $event->setCancelled(false);
    }

    public function onLogin(PlayerLoginEvent $event) {
        $player = $event->getPlayer();
        $player->getInventory()->clearAll();
        $player->setGamemode(0);
        $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
    }

    public function getArena(Player $player) {
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if ($player->getLevel()->getName() == $worldname) {
                return $worldname;
            }
        }
    }

    public function onDrop(PlayerDropItemEvent $ev){
        $ev->setCancelled(true);
    }

    public function isPlaying(Player $player) {
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if ($player->getLevel()->getName() == $worldname) {
                return TRUE;
            }
        }
    }
}

class MLGRushTask extends PluginTask implements Task {
    public $cfg;
    public $prefix = '§1M§fL§4G§fRush §8| §7';

    public function __construct(\pocketmine\plugin\Plugin $owner) {
        $this->plugin = $owner;
        parent::__construct($owner);
    }

    public function onRun($tick) {
        foreach ($this->getOwner()->getServer()->getOnlinePlayers() as $player) {
            if (!$player instanceof Player) {
                return;
            }
            if ($player->getLevel() == $this->getOwner()->getServer()->getDefaultLevel()) {
                $player->setImmobile(false);
            }

            $player->setHealth(20);
            $player->setFood(20);
        }

        $dir = $this->plugin->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        $this->cfg = new Config($this->getOwner()->getDataFolder() . 'config.yml', Config::YAML);
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            if (!$this->getOwner()->getServer()->getLevelByName($gamename) instanceof Level) {
                $this->getOwner()->getServer()->loadLevel($gamename);
                $this->getOwner()->getServer()->getLevelByName($gamename)->setTime(0);
                $this->getOwner()->getServer()->getLevelByName($gamename)->stopTime();
            }
            $arenafile = new Config($this->getOwner()->getDataFolder() . '/games/' . $gamename . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            $playercount = $arenafile->get('playercount');
            //ROUND1:
            if ($mode === 'waiting') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter + 1);
                $arenafile->save();
                if ($counter == 30) {
                    $arenafile->set('counter', 0);
                    $arenafile->save();
                    $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::RED . 'Waiting for 2 players!');
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                }
            } elseif ($mode === 'starting1') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
                            $p->getInventory()->setItem(0, Item::get(Item::STICK)->setCustomName(TextFormat::GOLD . 'Stick'));
                            $p->getInventory()->setItem(1, Item::get(Item::SANDSTONE, 0, 64));
                            $p->getInventory()->setItem(2, Item::get(Item::WOODEN_PICKAXE));

                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->addTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 1', '', 5, 15, 5);
                            $p->sendMessage($this->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');

                            $p->setImmobile(false);

                            $p->getLevel()->addSound(new ClickSound($p));
                            $arenafile->set('mode', 'ingame1');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame1') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 2) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 1) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "One" . TextFormat::GRAY . " second left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . 'The game has end!');
                            $p->getLevel()->addSound(new ClickSound($p));
                            $p->setImmobile(true);
                        }
                    }

                    $x1 = $arenafile->get('player1x');
                    $y1 = $arenafile->get('player1y');
                    $z1 = $arenafile->get('player1z');

                    $x2 = $arenafile->get('player2x');
                    $y2 = $arenafile->get('player2y');
                    $z2 = $arenafile->get('player2z');

                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'))->teleport(new Vector3($x1, $y1, $z1));
                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'))->teleport(new Vector3($x2, $y2, $z2));

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getLevelByName($gamename)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                    }

                    $arenafile->set('mode', 'starting2');
                    $arenafile->set('counter', 5);
                    $arenafile->set('blocks', array());
                    $arenafile->set('winner1', NULL);
                    $arenafile->save();
                }
                //ROUND2:
            } elseif ($mode === 'starting2') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
                            $p->getInventory()->setItem(0, Item::get(Item::STICK)->setCustomName(TextFormat::GOLD . 'Stick'));
                            $p->getInventory()->setItem(1, Item::get(Item::SANDSTONE, 0, 64));
                            $p->getInventory()->setItem(2, Item::get(Item::WOODEN_PICKAXE));
                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->addTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 2', '', 5, 15, 5);
                            $p->sendMessage($this->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');
                            $p->getLevel()->addSound(new ClickSound($p));
                            $p->setImmobile(false);
                            $arenafile->set('mode', 'ingame2');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame2') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::RED . 'Nobody wins this round!');
                            $p->getLevel()->addSound(new ClickSound($p));
                            $p->setImmobile(true);
                        }
                    }

                    $x1 = $arenafile->get('player1x');
                    $y1 = $arenafile->get('player1y');
                    $z1 = $arenafile->get('player1z');

                    $x2 = $arenafile->get('player2x');
                    $y2 = $arenafile->get('player2y');
                    $z2 = $arenafile->get('player2z');

                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'))->teleport(new Vector3($x1, $y1, $z1));
                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'))->teleport(new Vector3($x2, $y2, $z2));

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getLevelByName($gamename)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                    }

                    $arenafile->set('mode', 'starting3');
                    $arenafile->set('counter', 5);
                    $arenafile->set('blocks', array());
                    $arenafile->set('winner2', NULL);
                    $arenafile->save();
                }
                //ROUND3:
            } elseif ($mode === 'starting3') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getLevel()->addSound(new ClickSound($p));
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
                            $p->getInventory()->setItem(0, Item::get(Item::STICK)->setCustomName(TextFormat::GOLD . 'Stick'));
                            $p->getInventory()->setItem(1, Item::get(Item::SANDSTONE, 0, 64));
                            $p->getInventory()->setItem(2, Item::get(Item::WOODEN_PICKAXE));
                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->addTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 3', '', 5, 15, 5);
                            $p->sendMessage($this->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');
                            $p->getLevel()->addSound(new ClickSound($p));
                            $p->setImmobile(false);
                            $arenafile->set('mode', 'ingame3');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame3') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getLevelByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getLevel()->addSound(new ClickSound($p));
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            if (NULL !== $arenafile->get('winner3')) {
                            }
                            $p->teleport($this->getOwner()->getServer()->getDefaultLevel()->getSafeSpawn());
                            $p->getLevel()->addSound(new AnvilUseSound($p));
                            $p->setImmobile(false);
                        }
                    }

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getLevelByName($gamename)->setBlock(new Vector3($b['0'], $b['1'], $b['2']), Block::get(0));
                    }


                    $arenafile->set('blocks', array());
                    $arenafile->set('winner3', NULL);
                    $arenafile->save();

                    $winner1 = $arenafile->get('winner1');
                    $winner2 = $arenafile->get('winner2');
                    $winner3 = $arenafile->get('winner3');
                    //END:
                    $player1 = $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'));
                    $player2 = $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'));
                    $points1 = 0;
                    $points2 = 0;
                    //P1
                    if ($player1->getName() == $winner1) {
                        $points1 = $points1 + 1;
                    }
                    if ($player1->getName() == $winner2) {
                        $points1 = $points1 + 1;
                    }
                    if ($player1->getName() == $winner3) {
                        $points1 = $points1 + 1;
                    }
                    //P2
                    if ($player2->getName() == $winner1) {
                        $points2 = $points2 + 1;
                    }
                    if ($player2->getName() == $winner2) {
                        $points2 = $points2 + 1;
                    }
                    if ($player2->getName() == $winner3) {
                        $points2 = $points2 + 1;
                    }

                    foreach ($players as $p) {
                        $p->getInventory()->clearAll();
                        if ($p instanceof Player) {
                            $pname = $p->getName();
                            if ($points1 == $points2) {
                                $p->addTitle(TextFormat::GRAY . 'Undecited!');
                            } elseif ($points1 < $points2) {
                                if ($player2->getName() == $pname) {
                                    $p->addTitle(TextFormat::GOLD . 'You have won!!');
                                } else {
                                    $p->addTitle(TextFormat::RED . 'You have lost!');
                                }
                            } elseif ($points1 > $points2) {
                                if ($player1->getName() == $pname) {
                                    $p->addTitle(TextFormat::GOLD . 'You have won!');
                                } else {
                                    $p->addTitle(TextFormat::RED . 'You have lost!');
                                }
                            }
                        }
                    }
                    $arenafile->set('winner1', NULL);
                    $arenafile->set('winner2', NULL);
                    $arenafile->set('winner3', NULL);
                    $arenafile->set('mode', 'waiting');
                    $arenafile->set('counter', 0);
                    $arenafile->set('playercount', 0);
                    $arenafile->set('playerone', NULL);
                    $arenafile->set('playertwo', NULL);
                    $arenafile->save();

                    $tiles = $this->getOwner()->getServer()->getDefaultLevel()->getTiles();
                    foreach ($tiles as $tile) {
                        if ($tile instanceof Sign) {
                            $text = $tile->getText();
                            if ($text['0'] == "§1M§fL§4G§fRush") {
                                if (TextFormat::clean($text['1']) == $gamename) {
                                    $tile->setText(
                                        "§1M§fL§4G§fRush",
                                        $text['1'],
                                        TextFormat::YELLOW . '0/2',
                                        TextFormat::GREEN . 'JOIN'
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
