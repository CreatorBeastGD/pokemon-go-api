<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\Types;

use stdClass;

use function assert;
use function count;
use function preg_match;

final class Pokemon
{
    private int $dexNr;
    private string $id;
    private string $formId;
    private PokemonType $typePrimary;
    private ?PokemonType $typeSecondary;
    private ?PokemonStats $stats = null;
    /** @var TemporaryEvolution[] */
    private array $temporaryEvolutions = [];
    /** @var string[] */
    private array $quickMoveNames = [];
    /** @var string[] */
    private array $cinematicMoveNames = [];
    /** @var string[] */
    private array $eliteQuickMoveNames = [];
    /** @var string[] */
    private array $eliteCinematicMoveNames = [];
    /** @var PokemonForm[] */
    private array $pokemonForms = [];
    /** @var Pokemon[] */
    private array $pokemonRegionForms = [];

    public function __construct(
        int $dexNr,
        string $id,
        string $formId,
        PokemonType $typePrimary,
        ?PokemonType $typeSecondary
    ) {
        $this->dexNr         = $dexNr;
        $this->id            = $id;
        $this->formId        = $formId;
        $this->typePrimary   = $typePrimary;
        $this->typeSecondary = $typeSecondary;
    }

    public static function createFromGameMaster(stdClass $pokemonData): self
    {
        $pokemonParts = [];
        preg_match('~^V(?<id>\d{4})_POKEMON_(?<name>.*)$~i', $pokemonData->templateId, $pokemonParts);
        $pokemonSettings = $pokemonData->pokemonSettings;

        $secondaryType = null;
        if (isset($pokemonSettings->type2)) {
            $secondaryType = PokemonType::create($pokemonSettings->type2);
        }

        $pokemon = new self(
            (int) $pokemonParts['id'],
            $pokemonSettings->pokemonId,
            $pokemonParts['name'],
            PokemonType::create($pokemonSettings->type),
            $secondaryType
        );

        if (isset($pokemonSettings->stats->baseStamina)) {
            assert($pokemonSettings->stats instanceof stdClass);
            $pokemon->stats = new PokemonStats(
                $pokemonSettings->stats->baseStamina,
                $pokemonSettings->stats->baseAttack,
                $pokemonSettings->stats->baseDefense,
            );
        }

        $pokemon->quickMoveNames          = $pokemonSettings->quickMoves ?? [];
        $pokemon->cinematicMoveNames      = $pokemonSettings->cinematicMoves ?? [];
        $pokemon->eliteQuickMoveNames     = $pokemonSettings->eliteQuickMove ?? [];
        $pokemon->eliteCinematicMoveNames = $pokemonSettings->eliteCinematicMove ?? [];

        foreach ($pokemonSettings->tempEvoOverrides ?? [] as $evolutionBranch) {
            $pokemon->temporaryEvolutions[] = TemporaryEvolution::createFromGameMaster(
                $evolutionBranch,
                $pokemonSettings->pokemonId
            );
        }

        return $pokemon;
    }

    public function getDexNr(): int
    {
        return $this->dexNr;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getTypePrimary(): PokemonType
    {
        return $this->typePrimary;
    }

    public function getTypeSecondary(): ?PokemonType
    {
        return $this->typeSecondary;
    }

    public function getStats(): ?PokemonStats
    {
        return $this->stats;
    }

    /** @return string[] */
    public function getCinematicMoveNames(): array
    {
        return $this->cinematicMoveNames;
    }

    /** @return string[] */
    public function getEliteCinematicMoveNames(): array
    {
        return $this->eliteCinematicMoveNames;
    }

    /** @return string[] */
    public function getEliteQuickMoveNames(): array
    {
        return $this->eliteQuickMoveNames;
    }

    /** @return string[] */
    public function getQuickMoveNames(): array
    {
        return $this->quickMoveNames;
    }

    /** @return TemporaryEvolution[] */
    public function getTemporaryEvolutions(): array
    {
        return $this->temporaryEvolutions;
    }

    public function hasTemporaryEvolutions(): bool
    {
        return count($this->temporaryEvolutions) > 0;
    }

    public function addPokemonForm(PokemonForm $pokemonForm): void
    {
        $this->pokemonForms[] = $pokemonForm;
    }

    /** @return PokemonForm[] */
    public function getPokemonForms(): array
    {
        return $this->pokemonForms;
    }

    public function addPokemonRegionForm(Pokemon $pokemonRegionForm): void
    {
        $this->pokemonRegionForms[] = $pokemonRegionForm;
    }

    /** @return Pokemon[] */
    public function getPokemonRegionForms(): array
    {
        return $this->pokemonRegionForms;
    }
}