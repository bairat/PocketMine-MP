<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\tile;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\NetworkLittleEndianNBTStream;
use pocketmine\network\mcpe\protocol\BlockEntityDataPacket;
use pocketmine\Player;

abstract class Spawnable extends Tile{
	/** @var string|null */
	private $spawnCompoundCache = null;
	/** @var bool */
	private $dirty = true; //default dirty, until it's been spawned appropriately on the level

	/** @var NetworkLittleEndianNBTStream|null */
	private static $nbtWriter = null;

	public function createSpawnPacket() : BlockEntityDataPacket{
		$pk = new BlockEntityDataPacket();
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->namedtag = $this->getSerializedSpawnCompound();

		return $pk;
	}

	public function spawnTo(Player $player) : bool{
		if($this->closed){
			return false;
		}

		$player->sendDataPacket($this->createSpawnPacket());

		return true;
	}

	/**
	 * Flags the tile as modified, so that updates will be broadcasted at the next available opportunity.
	 * This MUST be called any time a change is made that players must be able to see.
	 */
	protected function onChanged() : void{
		$this->spawnCompoundCache = null;
		$this->dirty = true;
		$this->scheduleUpdate();
	}

	/**
	 * Returns whether the tile needs to be respawned to viewers.
	 *
	 * @return bool
	 */
	public function isDirty() : bool{
		return $this->dirty;
	}

	/**
	 * @param bool $dirty
	 */
	public function setDirty(bool $dirty = true) : void{
		$this->dirty = $dirty;
	}

	/**
	 * Returns encoded NBT (varint, little-endian) used to spawn this tile to clients. Uses cache where possible,
	 * populates cache if it is null.
	 *
	 * @return string encoded NBT
	 */
	final public function getSerializedSpawnCompound() : string{
		if($this->spawnCompoundCache === null){
			if(self::$nbtWriter === null){
				self::$nbtWriter = new NetworkLittleEndianNBTStream();
			}

			$this->spawnCompoundCache = self::$nbtWriter->write($this->getSpawnCompound());
		}

		return $this->spawnCompoundCache;
	}

	/**
	 * @return CompoundTag
	 */
	final public function getSpawnCompound() : CompoundTag{
		$nbt = new CompoundTag("", [
			new StringTag(self::TAG_ID, static::getSaveId()),
			new IntTag(self::TAG_X, $this->x),
			new IntTag(self::TAG_Y, $this->y),
			new IntTag(self::TAG_Z, $this->z)
		]);
		$this->addAdditionalSpawnData($nbt);
		return $nbt;
	}

	/**
	 * An extension to getSpawnCompound() for
	 * further modifying the generic tile NBT.
	 *
	 * @param CompoundTag $nbt
	 */
	abstract protected function addAdditionalSpawnData(CompoundTag $nbt) : void;

	/**
	 * Called when a player updates a block entity's NBT data
	 * for example when writing on a sign.
	 *
	 * @param CompoundTag $nbt
	 * @param Player      $player
	 *
	 * @return bool indication of success, will respawn the tile to the player if false.
	 */
	public function updateCompoundTag(CompoundTag $nbt, Player $player) : bool{
		return false;
	}
}
