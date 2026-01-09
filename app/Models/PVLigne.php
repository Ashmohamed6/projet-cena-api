<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;


/**
 * Modèle PVLigne
 * 
 * Représente une ligne du tableau d'un procès-verbal.
 * - Pour PV Arrondissement : 1 ligne = 1 village/quartier
 * - Pour PV Village : 1 ligne = 1 poste de vote
 * 
 * @property int $id
 * @property int $proces_verbal_id
 * @property int|null $village_quartier_id
 * @property int|null $centre_vote_id
 * @property int|null $poste_vote_id
 * @property int|null $ordre
 * @property int $bulletins_nuls
 * @property string|null $president_nom
 * @property string|null $president_signature_image
 * @property string|null $presidents_postes_text
 * @property int $version
 * @property int|null $operateur_user_id
 * @property \Carbon\Carbon|null $date_saisie
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @property-read ProcesVerbal $procesVerbal
 * @property-read VillageQuartier|null $villageQuartier
 * @property-read CentreVote|null $centreVote
 * @property-read PosteVote|null $posteVote
 * @property-read User|null $operateur
 * @property-read \Illuminate\Database\Eloquent\Collection|PVLigneResultat[] $resultats
 */
class PVLigne extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pv_lignes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'proces_verbal_id',
        'arrondissement_id',
        'village_quartier_id',
        'centre_vote_id',
        'poste_vote_id',
        'ordre',
        'bulletins_nuls',
        'president_nom',
        'president_signature_image',
        'presidents_postes_text',
        'version',
        'operateur_user_id',
        'date_saisie',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordre' => 'integer',
        'bulletins_nuls' => 'integer',
        'version' => 'integer',
        'date_saisie' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'bulletins_nuls' => 0,
        'version' => 1,
    ];

    /**
     * Boot method to auto-increment version on update
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($ligne) {
            $ligne->version++;
            $ligne->date_saisie = now();
        });

        static::creating(function ($ligne) {
            if (!$ligne->date_saisie) {
                $ligne->date_saisie = now();
            }
        });
    }

    // ==================== RELATIONS ====================

    /**
     * Procès-verbal parent
     */
    public function procesVerbal(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'proces_verbal_id');
    }

    /**
     * Village/Quartier (pour PV niveau arrondissement)
     */
    public function villageQuartier(): BelongsTo
    {
        return $this->belongsTo(VillageQuartier::class, 'village_quartier_id');
    }

    /**
     * Centre de vote (pour PV niveau village)
     */
    public function centreVote(): BelongsTo
    {
        return $this->belongsTo(CentreVote::class, 'centre_vote_id');
    }

    /**
     * Poste de vote (pour PV niveau village)
     */
    public function posteVote(): BelongsTo
    {
        return $this->belongsTo(PosteVote::class, 'poste_vote_id');
    }

    /**
     * Opérateur ayant saisi cette ligne
     */
    public function operateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operateur_user_id');
    }

    /**
     * Résultats détaillés pour cette ligne
     */
    public function resultats(): HasMany
    {
        return $this->hasMany(PVLigneResultat::class, 'pv_ligne_id');
    }

    /**
 * Arrondissement (pour PV niveau commune)
 */
public function arrondissement(): BelongsTo
{
    return $this->belongsTo(Arrondissement::class, 'arrondissement_id');
}

    // ==================== SCOPES ====================

    /**
     * Scope : Lignes pour mode village (village_quartier_id rempli)
     */
    public function scopeModeVillage($query)
    {
        return $query->whereNotNull('village_quartier_id');
    }

    /**
     * Scope : Lignes pour mode poste (poste_vote_id rempli)
     */
    public function scopeModePoste($query)
    {
        return $query->whereNotNull('poste_vote_id');
    }

    /**
     * Scope : Trier par ordre
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre');
    }

    // ==================== ACCESSORS ====================

    /**
     * Obtenir le nom de la localisation
     * (Village ou Poste selon le mode)
     */
    public function getNomLocalisationAttribute(): ?string
    {
        if ($this->villageQuartier) {
            return $this->villageQuartier->nom;
        }

        if ($this->posteVote) {
            return $this->posteVote->nom;
        }

        return null;
    }

    /**
     * Obtenir le type de ligne (village ou poste)
     */
    public function getTypeAttribute(): string
    {
        return $this->village_quartier_id ? 'village' : 'poste';
    }

    /**
     * Calculer le total des voix pour cette ligne
     */
    public function getTotalVoixAttribute(): int
    {
        return $this->resultats()->sum('nombre_voix');
    }

    /**
     * Calculer le total des votants (voix + bulletins nuls)
     */
    public function getTotalVotantsAttribute(): int
    {
        return $this->total_voix + $this->bulletins_nuls;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier si c'est une ligne village
     */
    public function estVillage(): bool
    {
        return $this->village_quartier_id !== null;
    }

    /**
     * Vérifier si c'est une ligne poste
     */
    public function estPoste(): bool
    {
        return $this->poste_vote_id !== null;
    }

    /**
     * Ajouter ou mettre à jour un résultat pour une candidature
     */
    public function setResultat(int $candidatureId, int $nombreVoix, ?int $operateurUserId = null): PVLigneResultat
    {
        return $this->resultats()->updateOrCreate(
            ['candidature_id' => $candidatureId],
            [
                'nombre_voix' => $nombreVoix,
                'operateur_user_id' => $operateurUserId ?? auth()->id(),
                'date_saisie' => now(),
            ]
        );
    }

    /**
     * Obtenir le résultat pour une candidature spécifique
     */
    public function getResultatPourCandidature(int $candidatureId): ?PVLigneResultat
    {
        return $this->resultats()->where('candidature_id', $candidatureId)->first();
    }


    public function recalculerTotaux(): void
{
    // Somme des voix sur la ligne
    $sumVoix = 0;

    if (method_exists($this, 'resultats')) {
        try {
            // suppose une colonne 'nombre_voix' dans la table des résultats
            $sumVoix = (int) $this->resultats()->sum('nombre_voix');
        } catch (\Throwable $e) {
            $sumVoix = 0;
        }
    }

    $nuls = (int) ($this->bulletins_nuls ?? 0);
    $totalVotants = $sumVoix + $nuls;

    // Mettre à jour uniquement les colonnes réellement présentes dans la table
    try {
        $table = $this->getTable();

        if (Schema::hasColumn($table, 'total_voix')) {
            $this->total_voix = $sumVoix;
        }

        if (Schema::hasColumn($table, 'nombre_suffrages_exprimes')) {
            $this->nombre_suffrages_exprimes = $sumVoix;
        }

        if (Schema::hasColumn($table, 'nombre_votants')) {
            $this->nombre_votants = $totalVotants;
        }

        if (Schema::hasColumn($table, 'total_votants')) {
            $this->total_votants = $totalVotants;
        }

        // Sauvegarder seulement si on a modifié quelque chose
        if ($this->isDirty()) {
            if (method_exists($this, 'saveQuietly')) {
                $this->saveQuietly();
            } else {
                $this->save();
            }
        }
    } catch (\Throwable $e) {
        // Ne pas bloquer l'update du PV si le recalcul ne peut pas s'appliquer
    }
}

}
