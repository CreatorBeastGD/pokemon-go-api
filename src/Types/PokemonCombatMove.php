<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\Types;

use stdClass;

final class PokemonCombatMove
{
    private float $power;
    private float $energy;
    private ?PokemonCombatMoveBuffs $buffs;

    public function __construct(float $power, float $energy, ?PokemonCombatMoveBuffs $buffs)
    {
        $this->power  = $power;
        $this->energy = $energy;
        $this->buffs  = $buffs;
    }

    public static function createFromGameMaster(stdClass $combatMoveData): self
    {
        $moveBuffs = null;
        if (isset($combatMoveData->combatMove->buffs)) {
            $moveBuffs = new PokemonCombatMoveBuffs(
                (int) ($combatMoveData->combatMove->buffs->buffActivationChance * 100),
                $combatMoveData->combatMove->buffs->attackerAttackStatStageChange ?? null,
                $combatMoveData->combatMove->buffs->attackerDefenseStatStageChange ?? null,
                $combatMoveData->combatMove->buffs->targetAttackStatStageChange ?? null,
                $combatMoveData->combatMove->buffs->targetDefenseStatStageChange ?? null,
            );
        }

        return new self(
            $combatMoveData->combatMove->power,
            $combatMoveData->combatMove->energyDelta,
            $moveBuffs
        );
    }

    public function getPower(): float
    {
        return $this->power;
    }

    public function getEnergy(): float
    {
        return $this->energy;
    }

    public function getBuffs(): ?PokemonCombatMoveBuffs
    {
        return $this->buffs;
    }
}
