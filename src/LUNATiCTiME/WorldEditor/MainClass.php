<?php

namespace LUNATiCTiME\WorldEditor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\command\{Command, CommandSender};

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\level\generator\object\OakTree;

use pocketmine\utils\{Config, Random};

use pocketmine\Player;

class MainClass extends PluginBase implements Listener {

	const TAG = "[§dWorldEditor§f]§6 ";

	private $config, $editor = [];

	public function onEnable()
	{
		if (!file_exists($this->getDataFolder())) mkdir($this->getDataFolder(), 0744, true);
		$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, ["wand" => 292]);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
	{
		if ($sender instanceof Player) {
			if ($label{0} === "/") $label = substr($label, 1);
			$name = $sender->getName();
			switch ($label) {
				case "set":
					if (!isset($args[0])) return false;
					$data = explode(":", $args[0]);
					if (!isset($data[1])) $data[1] = 0;
					$this->setBlocks($sender, (int) $data[0], (int) $data[1]);
					break;
				case "cut":
					$this->setBlocks($sender, 0, 0);
					break;
				case "air":
					if (!isset($args[0])) return false;
					$data = explode(":", $args[0]);
					if (!isset($data[1])) $data[1] = 0;
					$this->replaceBlocks($sender, (int) $data[0], (int) $data[1], 0, 0);
					break;
				case "replace":
					if (!isset($args[0], $args[1])) return false;
					$before = explode(":", $args[0]);
					if (!isset($before[1])) $before[1] = 0;
					$after = explode(":", $args[1]);
					if (!isset($after[1])) $after[1] = 0;
					$this->replaceBlocks($sender, (int) $before[0], (int) $before[1], (int) $after[0], (int) $after[1]);
					break;
				case "undo":
					$this->undoBlocks($sender);
					break;
				case "desel":
					unset($this->editor[$name]);
					$sender->sendMessage(self::TAG."選択した座標をリセットしました。");
					break;
				case "pos1":
					if (!isset($this->editor[$name][0])) {
						$pos = [floor($sender->x), floor($sender->y) - 1, floor($sender->z)];
						$this->setFirstSelection($sender, $pos);
					} else {
						$sender->sendMessage(self::TAG."座標1は設定済みです。");
					}
					break;
				case "pos2":
					if (!isset($this->editor[$name][1])) {
						$pos = [floor($sender->x), floor($sender->y) - 1, floor($sender->z)];
						$this->setSecondSelection($sender, $pos);
					} else {
						$sender->sendMessage(self::TAG."座標2は設定済みです。");
					}
					break;
				case "line":
					if (!isset($args[0])) return false;
					$data = explode(":", $args[0]);
					if (!isset($data[1])) $data[1] = 0;
					$this->createLine($sender, (int) $data[0], (int) $data[1]);
					break;
				case "sphere":
					if (!isset($args[0], $args[1])) return false;
					$data = explode(":", $args[0]);
					if (!isset($data[1])) $data[1] = 0;
					$this->createSphere($sender, (int) $data[0], (int) $data[1], $args[1]);
					break;
				default:
			}
			return true;
		} else {
			$sender->sendMessage(self::TAG."ゲーム内で使用して下さい。");
			return false;
		}
	}

	public function onPlayerInteract(PlayerInteractEvent $event)
	{
		if ($event->getItem()->getId() == $this->config->get("wand")) {
			$action = $event->getAction();
			if ($action == PlayerInteractEvent::LEFT_CLICK_BLOCK or $action == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
				$player = $event->getPlayer();
				if ($player->isOp() && $player->getGamemode() == 1) {
					$name = $player->getName();
					if (!isset($this->editor[$name][0])) {
						$block = $event->getBlock();
						$pos = [$block->x, $block->y, $block->z];
						$this->setFirstSelection($player, $pos);
						$event->setCancelled(true);
					}
				}
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event)
	{
		if ($event->getItem()->getId() == $this->config->get("wand")) {
			$player = $event->getPlayer();
			if ($player->isOp() && $player->getGamemode() == 1) {
				$name = $player->getName();
				if (!isset($this->editor[$name][1])) {
					$block = $event->getBlock();
					$pos = [$block->x, $block->y, $block->z];
					$this->setSecondSelection($player, $pos);
					$event->setCancelled(true);
				}
			}
		}
	}

	public function setFirstSelection(Player $player, array $pos)
	{
		$name = $player->getName();
		$this->editor[$name][0] = $pos;
		$message = self::TAG."座標1(".implode(", ", $pos).")を設定しました。";
		if (isset($this->editor[$name][1])) {
			$count = $this->countBlocks($name);
			$message = $message." (".$count." ブロック)";
		}
		$player->sendMessage($message);
	}

	public function setSecondSelection(Player $player, array $pos)
	{
		$name = $player->getName();
		$this->editor[$name][1] = $pos;
		$message = self::TAG."座標2(".implode(", ", $pos).")を設定しました。";
		if (isset($this->editor[$name][0])) {
			$count = $this->countBlocks($name);
			$message = $message." (".$count." ブロック)";
		}
		$player->sendMessage($message);
	}

	public function countBlocks(string $name)
	{
		$pos = $this->editor[$name];
		$startX = min($pos[0][0], $pos[1][0]);
		$endX = max($pos[0][0], $pos[1][0]);
		$startY = min($pos[0][1], $pos[1][1]);
		$endY = max($pos[0][1], $pos[1][1]);
		$startZ = min($pos[0][2], $pos[1][2]);
		$endZ = max($pos[0][2], $pos[1][2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}

	public function setBlocks(Player $player, int $id, int $meta)
	{
		$name = $player->getName();
		if (isset($this->editor[$name][0], $this->editor[$name][1])) {
			$timeStart = microtime(true);
			$pos = $this->editor[$name];
			$level = $player->level;
			$startX = min($pos[0][0], $pos[1][0]);
			$endX = max($pos[0][0], $pos[1][0]);
			$startY = min($pos[0][1], $pos[1][1]);
			$endY = max($pos[0][1], $pos[1][1]);
			$startZ = min($pos[0][2], $pos[1][2]);
			$endZ = max($pos[0][2], $pos[1][2]);
			$count = $this->countBlocks($name);
			$undo = [];
			for ($x = $startX; $x <= $endX; ++$x) {
				for ($y = $startY; $y <= $endY; ++$y) {
					for ($z = $startZ; $z <= $endZ; ++$z) {
						$undo[$x.":".$y.":".$z] = $level->getBlockIdAt($x, $y, $z).":".$level->getBlockDataAt($x, $y, $z);
						$level->setBlockIdAt($x, $y, $z, $id);
						$level->setBlockDataAt($x, $y, $z, $meta);
					}
				}
			}
			$this->editor[$name][2] = $undo;
			$level->clearChunkCache($player->getFloorX() >> 4, $player->getFloorZ() >> 4);
			$level->requestChunk($player->getFloorX() >> 4, $player->getFloorZ() >> 4, $player);
			$timeEnd = microtime(true);
			$time = $timeEnd - $timeStart;
			$player->sendMessage(self::TAG.$count."個のブロックを変更しました。(処理時間:".round($time, 2)."秒)");
		} else {
			$player->sendMessage(self::TAG."まず座標を設定して下さい。");
		}
	}

	public function undoBlocks(Player $player)
	{
		$name = $player->getName();
		if (isset($this->editor[$name][2])) {
			$timeStart = microtime(true);
			$level = $player->level;
			$count = $this->countBlocks($name);
			foreach ($this->editor[$name][2] as $key => $value) {
				$pos = explode(":", $key);
				$block = explode(":", $value);
				$level->setBlockIdAt($pos[0], $pos[1], $pos[2], $block[0]);
				$level->setBlockDataAt($pos[0], $pos[1], $pos[2], $block[1]);
			}
			$timeEnd = microtime(true);
			$time = $timeEnd - $timeStart;
			$player->sendMessage(self::TAG.$count."個のブロックを復元しました。(処理時間:".round($time, 2)."秒)");
		} else {
			$player->sendMessage(self::TAG."まず座標を設定して下さい。");
		}
	}

	public function replaceBlocks(Player $player, int $id1, int $meta1, int $id2, int $meta2)
	{
		$name = $player->getName();
		if (isset($this->editor[$name][0], $this->editor[$name][1])) {
			$timeStart = microtime(true);
			$pos = $this->editor[$name];
			$level = $player->level;
			$startX = min($pos[0][0], $pos[1][0]);
			$endX = max($pos[0][0], $pos[1][0]);
			$startY = min($pos[0][1], $pos[1][1]);
			$endY = max($pos[0][1], $pos[1][1]);
			$startZ = min($pos[0][2], $pos[1][2]);
			$endZ = max($pos[0][2], $pos[1][2]);
			$count = $this->countBlocks($name);
			$undo = [];
			for ($x = $startX; $x <= $endX; ++$x) {
				for ($y = $startY; $y <= $endY; ++$y) {
					for ($z = $startZ; $z <= $endZ; ++$z) {
						$undo[$x.":".$y.":".$z] = $level->getBlockIdAt($x, $y, $z).":".$level->getBlockDataAt($x, $y, $z);
						if ($level->getBlockIdAt($x, $y, $z) == $id1 && $level->getBlockDataAt($x, $y, $z) == $meta1) {
							$level->setBlockIdAt($x, $y, $z, $id2);
							$level->setBlockDataAt($x, $y, $z, $meta2);
						}
					}
				}
			}
			$this->editor[$name][2] = $undo;
			$timeEnd = microtime(true);
			$time = $timeEnd - $timeStart;
			$player->sendMessage(self::TAG.$count."個のブロックを変更しました。(処理時間:".round($time, 2)."秒)");
		} else {
			$player->sendMessage(self::TAG."まず座標を設定して下さい。");
		}
	}

	public function createLine(Player $player, int $id, int $meta)
	{
		$name = $player->getName();
		if (isset($this->editor[$name][0], $this->editor[$name][1])) {
			$timeStart = microtime(true);
			$pos = $this->editor[$name];
			$level = $player->level;
			$startX = $pos[0][0];
			$endX = $pos[1][0];
			$startY = $pos[0][1];
			$endY = $pos[1][1];
			$startZ = $pos[0][2];
			$endZ = $pos[1][2];
			$distance = sqrt(($endX - $startX + 1) ** 2 + ($endY - $startY + 1) ** 2 + ($endZ - $startZ + 1) ** 2);
			$pitch = rad2deg(atan2($endY - $startY, sqrt(($endX - $startX) ** 2 + ($endZ - $startZ) ** 2))) ;
			$yaw = rad2deg(atan2($endZ - $startZ, $endX - $startX)) - 90;
			$x = -sin($yaw * (M_PI / 180)) * cos($pitch * (M_PI / 180));
			$y = sin($pitch * (M_PI / 180));
			$z = cos($yaw * (M_PI / 180)) * cos($pitch * (M_PI / 180));
			$undo = [];
			for ($i = 0; $i < $distance; ++$i) {
				$xx = floor($startX);
				$yy = floor($startY);
				$zz = floor($startZ);
				if (!isset($undo[$xx.":".$yy.":".$zz])) $undo[$xx.":".$yy.":".$zz] = $level->getBlockIdAt($xx, $yy, $zz).":".$level->getBlockDataAt($xx, $yy, $zz);
				$level->setBlockIdAt($xx, $yy, $zz, $id);
				$level->setBlockDataAt($xx, $yy, $zz, $meta);
				$startX += $x;
				$startY += $y;
				$startZ += $z;
			}
			$this->editor[$name][2] = $undo;
			$timeEnd = microtime(true);
			$time = $timeEnd - $timeStart;
			$player->sendMessage(self::TAG."座標1,2間の線を作成しました。(処理時間:".round($time, 2)."秒)");
		} else {
			$player->sendMessage(self::TAG."まず座標を設定して下さい。");
		}
	}

	public function lengthSq($x, $y, $z)
	{
		return ($x * $x) + ($y * $y) + ($z * $z);
	}

	public function createSphere(Player $player, int $id, int $meta, int $radius)
	{
		$name = $player->getName();
		if (isset($this->editor[$name][0])) {
			$timeStart = microtime(true);
			$pos = $this->editor[$name][0];
			$count = 0;
			$radiusX = $radius + 0.5;
			$radiusY = $radius + 0.5;
			$radiusZ = $radius + 0.5;
			$invRadiusX = 1 / $radiusX;
			$invRadiusY = 1 / $radiusY;
			$invRadiusZ = 1 / $radiusZ;
			$ceilRadiusX = (int) ceil($radiusX);
			$ceilRadiusY = (int) ceil($radiusY);
			$ceilRadiusZ = (int) ceil($radiusZ);
			$nextXn = 0;
			$breakX = false;
			$level = $player->level;
			$pos = $this->editor[$player->getName()][0];
			for ($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x) {
				$xn = $nextXn;
				$nextXn = ($x + 1) * $invRadiusX;
				$nextYn = 0;
				$breakY = false;
				for ($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y ){
					$yn = $nextYn;
					$nextYn = ($y + 1) * $invRadiusY;
					$nextZn = 0;
					$breakZ = false;
					for ($z = 0; $z <= $ceilRadiusZ; ++$z) {
						$zn = $nextZn;
						$nextZn = ($z + 1) * $invRadiusZ;
						$distanceSq = $this->lengthSq($xn, $yn, $zn);
						if ($distanceSq > 1) {
							if ($z === 0) {
								if ($y === 0) {
									$breakX = true;
									$breakY = true;
									break;
								}
								$breakY = true;
								break;
							}
							break;
						}
						$level->setBlockIdAt($pos[0] + $x, $pos[1] + $y, $pos[2] + $z, $id);
						$level->setBlockIdAt($pos[0] + $x, $pos[1] + $y, $pos[2] - $z, $id);
						$level->setBlockIdAt($pos[0] + $x, $pos[1] - $y, $pos[2] + $z, $id);	
						$level->setBlockIdAt($pos[0] + $x, $pos[1] - $y, $pos[2] - $z, $id);
						$level->setBlockIdAt($pos[0] - $x, $pos[1] + $y, $pos[2] + $z, $id);
						$level->setBlockIdAt($pos[0] - $x, $pos[1] + $y, $pos[2] - $z, $id);
						$level->setBlockIdAt($pos[0] - $x, $pos[1] - $y, $pos[2] + $z, $id);
						$level->setBlockIdAt($pos[0] - $x, $pos[1] - $y, $pos[2] - $z, $id);
						$level->setBlockDataAt($pos[0] + $x, $pos[1] + $y, $pos[2] + $z, $meta);
						$level->setBlockDataAt($pos[0] + $x, $pos[1] + $y, $pos[2] - $z, $meta);
						$level->setBlockDataAt($pos[0] + $x, $pos[1] - $y, $pos[2] + $z, $meta);
						$level->setBlockDataAt($pos[0] + $x, $pos[1] - $y, $pos[2] - $z, $meta);
						$level->setBlockDataAt($pos[0] - $x, $pos[1] + $y, $pos[2] + $z, $meta);
						$level->setBlockDataAt($pos[0] - $x, $pos[1] + $y, $pos[2] - $z, $meta);
						$level->setBlockDataAt($pos[0] - $x, $pos[1] - $y, $pos[2] + $z, $meta);
						$level->setBlockDataAt($pos[0] - $x, $pos[1] - $y, $pos[2] - $z, $meta);
					}
				}
			}
			$timeEnd = microtime(true);
			$time = $timeEnd - $timeStart;
			$player->sendMessage(self::TAG."半径".$radius."の球を作成しました。(処理時間:".round($time, 2)."秒)");
		} else {
			$player->sendMessage(self::TAG."まず座標1を設定して下さい。");
		}
	}
}
