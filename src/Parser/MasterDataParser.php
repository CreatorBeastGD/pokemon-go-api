<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\Parser;

use Exception;
use PokemonGoLingen\PogoAPI\Collections\AttacksCollection;
use PokemonGoLingen\PogoAPI\Collections\PokemonCollection;
use PokemonGoLingen\PogoAPI\Types\Pokemon;
use PokemonGoLingen\PogoAPI\Types\PokemonCombatMove;
use PokemonGoLingen\PogoAPI\Types\PokemonFormCollection;
use PokemonGoLingen\PogoAPI\Types\PokemonMove;
use stdClass;

use function assert;
use function file_get_contents;
use function json_decode;
use function preg_match;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

class MasterDataParser
{
    private PokemonCollection $pokemonCollection;
    private AttacksCollection $attacksCollection;

    public function __construct()
    {
        $this->pokemonCollection = new PokemonCollection();
        $this->attacksCollection = new AttacksCollection();
    }

    public function parseFile(string $gameMasterFile): void
    {
        $fileContent = file_get_contents($gameMasterFile);
        if ($fileContent === false) {
            throw new Exception('file does not exists');
        }

        $list                    = json_decode($fileContent, false, 512, JSON_THROW_ON_ERROR);
        $this->attacksCollection = $this->parseMoves($list);
        $this->pokemonCollection = $this->parsePokemon($list);

        $this->addCombatMoves($list, $this->attacksCollection);
        $this->addTemporaryEvolutions($list, $this->pokemonCollection);
        $this->addForms($list, $this->pokemonCollection);
    }

    public function getAttacksCollection(): AttacksCollection
    {
        return $this->attacksCollection;
    }

    public function getPokemonCollection(): PokemonCollection
    {
        return $this->pokemonCollection;
    }

    /**
     * @param array<int, stdClass> $list
     */
    private function parsePokemon(array $list): PokemonCollection
    {
        $pokemonCollection = new PokemonCollection();
        foreach ($list as $item) {
            $matches = [];
            if (
                ! preg_match('~^v(?<DexNr>[0-9]{4})_POKEMON_.*~i', $item->templateId, $matches)
                || ! isset($item->data->pokemonSettings)
            ) {
                continue;
            }

            assert($item->data instanceof stdClass);

            $pokemon = Pokemon::createFromGameMaster($item->data);

            if (
                strpos($pokemon->getFormId(), '_PURIFIED') !== false ||
                strpos($pokemon->getFormId(), '_SHADOW') !== false ||
                strpos($pokemon->getFormId(), '_NORMAL') !== false ||
                strpos($pokemon->getFormId(), '_COPY') !== false ||
                preg_match('~_\d{4}$~', $pokemon->getFormId())
            ) {
                continue;
            }

            $basePokemon = $pokemonCollection->get($pokemon->getId());
            if ($basePokemon !== null) {
                $basePokemon->addPokemonRegionForm($pokemon);
                continue;
            }

            $pokemonCollection->add($pokemon);
        }

        return $pokemonCollection;
    }

    /**
     * @param array<int, stdClass> $list
     */
    private function parseMoves(array $list): AttacksCollection
    {
        $attacksCollection = new AttacksCollection();
        foreach ($list as $item) {
            $matches = [];
            if (! preg_match('~^v(?<MoveId>[0-9]{4})_MOVE_(?<MoveName>.*)$~i', $item->templateId, $matches)) {
                continue;
            }

            $attacksCollection->add(
                PokemonMove::createFromGameMaster($item->data)
            );
        }

        return $attacksCollection;
    }

    /**
     * @param array<int, stdClass> $list
     */
    private function addCombatMoves(array $list, AttacksCollection $attacksCollection): void
    {
        foreach ($list as $item) {
            $matches = [];
            if (! preg_match('~^COMBAT_V(?<MoveId>[0-9]{4})_MOVE_(?<MoveName>.*)$~i', $item->templateId, $matches)) {
                continue;
            }

            $moveId = (int) $matches['MoveId'];
            $move   = $attacksCollection->getById($moveId);
            if ($move === null || ! isset($item->data->combatMove->power)) {
                continue;
            }

            assert($item->data instanceof stdClass);

            $move->setCombatMove(PokemonCombatMove::createFromGameMaster($item->data));
        }
    }

    /**
     * @param array<int, stdClass> $list
     */
    private function addTemporaryEvolutions(array $list, PokemonCollection $pokemonCollection): void
    {
        foreach ($list as $item) {
            $matches = [];
            if (
                ! preg_match(
                    '~^TEMPORARY_EVOLUTION_V(?<DexNr>[0-9]{4})_POKEMON_(.*)$~i',
                    $item->templateId,
                    $matches
                )
            ) {
                continue;
            }

            $dexNr   = (int) $matches['DexNr'];
            $pokemon = $pokemonCollection->getByDexId($dexNr);
            if ($pokemon === null) {
                continue;
            }

            $temporaryEvolutions = $pokemon->getTemporaryEvolutions();
            foreach ($temporaryEvolutions as $temporaryEvolution) {
                $temporaryEvolutions[$temporaryEvolution->getId()] = $temporaryEvolution;
            }

            foreach ($item->data->temporaryEvolutionSettings->temporaryEvolutions as $temporaryEvolution) {
                $temporaryEvolutionId = $item->data->temporaryEvolutionSettings->pokemonId
                    . substr($temporaryEvolution->temporaryEvolutionId, 14); // trim TEMP_EVOLUTION
                $temporaryEvolutions[$temporaryEvolutionId]->setAssetBundleId($temporaryEvolution->assetBundleValue);
            }
        }
    }

    /**
     * @param array<int, stdClass> $list
     */
    private function addForms(array $list, PokemonCollection $pokemonCollection): void
    {
        foreach ($list as $item) {
            $matches = [];
            if (! preg_match('~^FORMS_V(?<DexNr>\d{4})_POKEMON_(?<name>.*)$~i', $item->templateId, $matches)) {
                continue;
            }

            $dexNr   = (int) $matches['DexNr'];
            $pokemon = $pokemonCollection->getByDexId($dexNr);
            if ($pokemon === null) {
                continue;
            }

            $pokemonFormCollection = PokemonFormCollection::createFromGameMaster($item->data);

            foreach ($pokemonFormCollection->getPokemonForms() as $pokemonForm) {
                if ($pokemonForm->getId() === $pokemon->getId() . '_NORMAL') {
                    $pokemon->setPokemonForm($pokemonForm);
                    continue;
                }

                foreach ($pokemon->getPokemonRegionForms() as $pokemonRegionForm) {
                    if ($pokemonRegionForm->getFormId() !== $pokemonForm->getId()) {
                        continue;
                    }

                    $pokemonRegionForm->setPokemonForm($pokemonForm);
                }
            }
        }
    }
}
