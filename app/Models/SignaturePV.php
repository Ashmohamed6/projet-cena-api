<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle SignaturePV
 * 
 * Représente une signature sur un procès-verbal.
 * Peut être :
 * - Un délégué de parti politique
 * - Un président de poste de vote
 * - Un coordonnateur
 * - Un superviseur
 * 
 * @property int $id
 * @property int $proces_verbal_id
 * @property int|null $parti_politique_id
 * @property string|null $type_signataire
 * @property string|null $nom_signataire
 * @property string|null $fonction
 * @property string|null $signature_image
 * @property bool $a_signe
 * @property string|null $motif_absence
 * @property int|null $ordre
 * @property \Carbon\Carbon|null $date_signature
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @property-read ProcesVerbal $procesVerbal
 * @property-read EntitePolitique|null $partiPolitique
 */
class SignaturePV extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'signatures_pv';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'proces_verbal_id',
        'parti_politique_id',
        'type_signataire',
        'nom_signataire',
        'fonction',
        'signature_image',
        'a_signe',
        'motif_absence',
        'ordre',
        'date_signature',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'a_signe' => 'boolean',
        'ordre' => 'integer',
        'date_signature' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'a_signe' => true,
        'type_signataire' => 'delegue_parti',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($signature) {
            // Auto-remplir la date de signature si le délégué a signé
            if ($signature->a_signe && !$signature->date_signature) {
                $signature->date_signature = now();
            }

            // Auto-remplir IP et User Agent si disponibles
            if (request()) {
                $signature->ip_address = $signature->ip_address ?? request()->ip();
                $signature->user_agent = $signature->user_agent ?? request()->userAgent();
            }
        });
    }

    // ==================== RELATIONS ====================

    /**
     * Procès-verbal concerné
     */
    public function procesVerbal(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'proces_verbal_id');
    }

    /**
     * Parti politique (pour délégués de parti)
     */
    public function partiPolitique(): BelongsTo
    {
        return $this->belongsTo(EntitePolitique::class, 'parti_politique_id');
    }

    /**
     * Alias pour partiPolitique (pour cohérence avec le nom de la table)
     */
    public function entitePolitique(): BelongsTo
    {
        return $this->partiPolitique();
    }

    // ==================== SCOPES ====================

    /**
     * Scope : Signatures des délégués de parti
     */
    public function scopeDeleguesParti($query)
    {
        return $query->where('type_signataire', 'delegue_parti');
    }

    /**
     * Scope : Signatures des présidents de poste
     */
    public function scopePresidentsPoste($query)
    {
        return $query->where('type_signataire', 'president_poste');
    }

    /**
     * Scope : Signatures effectuées
     */
    public function scopeAvecSignature($query)
    {
        return $query->where('a_signe', true);
    }

    /**
     * Scope : Absences
     */
    public function scopeAbsents($query)
    {
        return $query->where('a_signe', false);
    }

    /**
     * Scope : Trier par ordre
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre')->orderBy('created_at');
    }

    // ==================== ACCESSORS ====================

    /**
     * Obtenir le nom complet du signataire
     * (depuis nom_signataire ou depuis le parti)
     */
    public function getNomCompletAttribute(): ?string
    {
        if ($this->nom_signataire) {
            return $this->nom_signataire;
        }

        if ($this->partiPolitique) {
            return "Délégué " . $this->partiPolitique->sigle;
        }

        return null;
    }

    /**
     * Obtenir le statut de signature (Signé/Absent)
     */
    public function getStatutSignatureAttribute(): string
    {
        return $this->a_signe ? 'Signé' : 'Absent';
    }

    /**
     * Obtenir l'icône de statut
     */
    public function getIconeStatutAttribute(): string
    {
        return $this->a_signe ? '✓' : '✗';
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Marquer comme signé
     */
    public function marquerSigne(?string $signatureImage = null): void
    {
        $this->update([
            'a_signe' => true,
            'motif_absence' => null,
            'signature_image' => $signatureImage ?? $this->signature_image,
            'date_signature' => now(),
        ]);
    }

    /**
     * Marquer comme absent
     */
    public function marquerAbsent(string $motif): void
    {
        $this->update([
            'a_signe' => false,
            'motif_absence' => $motif,
            'signature_image' => null,
            'date_signature' => null,
        ]);
    }

    /**
     * Vérifier si c'est un délégué de parti
     */
    public function estDelegueParti(): bool
    {
        return $this->type_signataire === 'delegue_parti';
    }

    /**
     * Vérifier si c'est un président de poste
     */
    public function estPresidentPoste(): bool
    {
        return $this->type_signataire === 'president_poste';
    }

    /**
     * Vérifier si c'est un coordonnateur
     */
    public function estCoordonnateur(): bool
    {
        return $this->type_signataire === 'coordonnateur';
    }

    /**
     * Obtenir la représentation pour le PDF
     */
    public function toPdfArray(): array
    {
        return [
            'parti' => $this->partiPolitique?->sigle ?? 'N/A',
            'nom' => $this->nom_signataire ?? 'Non renseigné',
            'a_signe' => $this->a_signe,
            'statut' => $this->statut_signature,
            'motif_absence' => $this->motif_absence,
            'fonction' => $this->fonction,
            'ordre' => $this->ordre,
        ];
    }
}
