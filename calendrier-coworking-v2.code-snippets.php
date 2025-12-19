<?php

/**
 * =============================================================================
 * CALENDRIER COWORKING V2 - FRONTEND BOOKING INTERFACE
 * =============================================================================
 *
 * Application de calendrier interactive pour la sélection de dates et la
 * réservation d'espaces de coworking. Interface utilisateur premium avec
 * support multi-quantité et calcul dynamique des prix.
 *
 * FONCTIONNALITÉS :
 *
 * 1. CALENDRIER INTERACTIF
 *    - Affichage mensuel avec navigation fluide
 *    - Sélection de plages de dates (clic & drag)
 *    - États visuels : disponible, occupé, sélectionné, aujourd'hui
 *    - Adaptation automatique à la capacité restante
 *
 * 2. FORMULES FLEXIBLES
 *    - Journée / Demi-journée / Semaine / Mois
 *    - Calcul automatique des dates de fin
 *    - Tarification dynamique selon la formule
 *
 * 3. GESTION DES QUANTITÉS
 *    - Sélection du nombre de places (1 à capacité max)
 *    - Vérification de disponibilité en temps réel
 *    - Affichage des places restantes par jour
 *
 * 4. INTÉGRATION WOOCOMMERCE
 *    - Ajout au panier via REST API (AJAX)
 *    - Redirection automatique vers le checkout
 *    - Données de réservation stockées en meta
 *
 * DESIGN SYSTEM :
 * - CSS Variables pour la cohérence visuelle
 * - Palette SkyLounge (#1e73be primary, #10b981 success)
 * - Responsive mobile-first
 * - Animations fluides (transitions CSS)
 *
 * DÉPENDANCES :
 * - coworking-reservation-donnees.php : Données initiales (window.COWORKING_DATA)
 * - systeme-disponibilite.php : API REST /coworking/v1/availability/
 * - coworking-booking-engine-v2.php : API REST /coworking/v1/add-to-cart/
 *
 * @package    SkyLounge_Coworking
 * @subpackage Frontend
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    2.0.0
 *
 * @see coworking-booking-engine-v2.php  Moteur de réservation (backend)
 * @see systeme-disponibilite.php        Calcul des disponibilités
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SHORTCODE [coworking_calendar]
   =============================================================================
   Rendu du calendrier de réservation avec JavaScript intégré.
============================================================================= */

/**
 * Génère le HTML et JavaScript du calendrier de réservation.
 *
 * Ce shortcode injecte :
 * - Le CSS complet (variables, grid, responsive)
 * - La structure HTML du calendrier
 * - Le JavaScript vanilla (pas de dépendance React/Vue)
 *
 * @since 1.0.0
 *
 * @return string HTML complet du calendrier avec CSS et JS inline.
 */
add_shortcode('coworking_calendar', 'render_coworking_calendar_optimized');

/**
 * Fonction de rendu du calendrier coworking optimisé.
 *
 * Récupère les tarifs depuis ACF et génère l'interface complète.
 * Utilise output buffering pour capturer le HTML/CSS/JS.
 *
 * @since 2.0.0
 *
 * @global int $post L'offre coworking courante.
 *
 * @return string Le HTML complet du calendrier.
 */
function render_coworking_calendar_optimized() {
    $offre_id = get_the_ID();
    
    // Récupération des tarifs depuis ACF
    $price_day = (float) get_field('prix_journee', $offre_id);
    $price_week = (float) get_field('prix_semaine', $offre_id);
    $price_month = (float) get_field('prix_mois', $offre_id);

    ob_start();
    ?>

<!-- ==========================================================================
     CSS DU CALENDRIER - DESIGN SYSTEM SKYLOUNGE
     ========================================================================== -->
<style>
/* Variables CSS - Palette SkyLounge */
:root {
    --cw-primary: #1e73be;
    --cw-primary-light: #5AB7E2;
    --cw-primary-pale: #e8f4fd;
    --cw-primary-dark: #155a96;
    --cw-success: #10b981;
    --cw-warning: #f59e0b;
    --cw-danger: #ef4444;
    --cw-gray-50: #f9fafb;
    --cw-gray-100: #f3f4f6;
    --cw-gray-200: #e5e7eb;
    --cw-gray-300: #d1d5db;
    --cw-gray-400: #9ca3af;
    --cw-gray-500: #6b7280;
    --cw-gray-600: #4b5563;
    --cw-gray-700: #374151;
    --cw-gray-800: #1f2937;
    --cw-gray-900: #111827;
}

/* Container principal */
.coworking-calendar-container {
    max-width: 500px;
    margin: 0 auto;
    display: none;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}

.coworking-calendar-container.active {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.cw-card {
    background: #fff;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-top: 24px;
    border: 1px solid var(--cw-gray-200);
}

/* Header */
.cw-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--cw-gray-200);
}

.cw-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--cw-gray-900);
    margin: 0;
}

.cw-formule-badge {
    background: var(--cw-primary-pale);
    color: var(--cw-primary);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: capitalize;
}

/* Navigation mois */
.cw-nav {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.cw-month-label {
    font-size: 16px;
    font-weight: 700;
    color: var(--cw-gray-800);
    min-width: 160px;
    text-align: center;
    text-transform: capitalize;
    user-select: none;
}

.cw-nav-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--cw-gray-200);
    background: #fff;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    color: var(--cw-gray-600);
    font-size: 18px;
    font-weight: 500;
}

.cw-nav-btn:hover {
    border-color: var(--cw-primary);
    background: var(--cw-primary-pale);
    color: var(--cw-primary);
}

/* Légende */
.cw-legend {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    justify-content: center;
    flex-wrap: wrap;
}

.cw-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--cw-gray-500);
}

.cw-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 4px;
}

.cw-legend-dot.available {
    background: var(--cw-primary-pale);
    border: 1px solid var(--cw-primary);
}

.cw-legend-dot.unavailable {
    background: var(--cw-gray-100);
    border: 1px solid var(--cw-gray-300);
}

.cw-legend-dot.selected {
    background: var(--cw-primary);
}

/* Grille calendrier */
.cw-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    position: relative;
    margin-bottom: 24px;
}

.cw-day-header {
    text-align: center;
    padding: 10px 0;
    font-size: 11px;
    font-weight: 700;
    color: var(--cw-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cw-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 500;
    border-radius: 10px;
    cursor: pointer;
    position: relative;
    color: var(--cw-gray-800);
    background: #fff;
    border: 2px solid transparent;
    transition: all 0.15s;
    user-select: none;
}

.cw-day.available {
    background: var(--cw-primary-pale);
    border-color: transparent;
}

.cw-day.available:hover {
    background: var(--cw-primary-light);
    color: #fff;
    transform: scale(1.08);
    z-index: 2;
}

.cw-day.unavailable,
.cw-day.full {
    background: var(--cw-gray-100);
    color: var(--cw-gray-400);
    cursor: not-allowed;
    pointer-events: none;
}

.cw-day.past {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}

.cw-day.low::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    background: var(--cw-warning);
    border-radius: 50%;
}

.cw-day.selected {
    background: var(--cw-primary) !important;
    color: #fff !important;
    border-color: var(--cw-primary) !important;
    font-weight: 700;
    z-index: 3;
    box-shadow: 0 4px 12px rgba(30, 115, 190, 0.35);
}

.cw-day.in-range {
    background: var(--cw-primary-pale);
    border-color: var(--cw-primary);
    color: var(--cw-primary);
    border-radius: 4px;
}

.cw-day.range-start {
    border-radius: 10px 4px 4px 10px;
}

.cw-day.range-end {
    border-radius: 4px 10px 10px 4px;
}

/* Sélecteur de quantité */
.cw-quantity-section {
    background: var(--cw-gray-50);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    display: none;
}

.cw-quantity-section.active {
    display: block;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.cw-quantity-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--cw-gray-700);
    margin-bottom: 12px;
    display: block;
}

.cw-quantity-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
}

.cw-qty-btn {
    width: 44px;
    height: 44px;
    border: 2px solid var(--cw-gray-200);
    background: #fff;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 600;
    color: var(--cw-gray-600);
    transition: all 0.2s;
}

.cw-qty-btn:hover:not(:disabled) {
    border-color: var(--cw-primary);
    background: var(--cw-primary-pale);
    color: var(--cw-primary);
}

.cw-qty-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.cw-qty-display {
    min-width: 120px;
    text-align: center;
}

.cw-qty-number {
    font-size: 32px;
    font-weight: 800;
    color: var(--cw-gray-900);
    line-height: 1;
}

.cw-qty-unit {
    font-size: 13px;
    color: var(--cw-gray-500);
    margin-top: 4px;
}

/* Résumé */
.cw-summary {
    background: linear-gradient(135deg, var(--cw-primary) 0%, var(--cw-primary-light) 100%);
    border-radius: 16px;
    padding: 24px;
    color: #fff;
    display: none;
}

.cw-summary.active {
    display: block;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.cw-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.cw-summary-row:last-of-type {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.cw-summary-label {
    font-size: 13px;
    opacity: 0.9;
}

.cw-summary-value {
    font-size: 15px;
    font-weight: 600;
}

.cw-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.cw-summary-total-label {
    font-size: 14px;
    font-weight: 500;
}

.cw-summary-total-price {
    font-size: 32px;
    font-weight: 800;
    letter-spacing: -1px;
}

.cw-reserve-btn {
    width: 100%;
    background: #fff;
    color: var(--cw-primary);
    border: none;
    padding: 16px 24px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.cw-reserve-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.cw-reserve-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}

/* Loading */
.cw-loading {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    font-size: 14px;
    font-weight: 600;
    color: var(--cw-primary);
    border-radius: 12px;
}

/* Toast */
.cw-toast {
    position: fixed;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    bottom: 30px;
    background: var(--cw-gray-800);
    color: #fff;
    padding: 14px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    z-index: 10000;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s ease;
    max-width: 90vw;
}

.cw-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.cw-toast.error { background: var(--cw-danger); }
.cw-toast.success { background: var(--cw-success); }

/* Animation shake */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}
.shake { animation: shake 0.4s ease-in-out; }

/* Responsive */
@media (max-width: 520px) {
    .cw-card { padding: 20px; }
    .cw-header { flex-direction: column; gap: 12px; text-align: center; }
    .cw-day { font-size: 13px; }
    .cw-qty-number { font-size: 28px; }
    .cw-summary-total-price { font-size: 28px; }
}
</style>

<div class="coworking-calendar-container" id="cw-container" data-offre-id="<?php echo esc_attr($offre_id); ?>">
    <div class="cw-card">
        <div class="cw-header">
            <h2 class="cw-title">Choisissez vos dates</h2>
            <span class="cw-formule-badge" id="cw-formule-badge">-</span>
        </div>

        <div class="cw-nav">
            <button class="cw-nav-btn" id="cw-prev" aria-label="Mois précédent">‹</button>
            <div class="cw-month-label" id="cw-month-label"></div>
            <button class="cw-nav-btn" id="cw-next" aria-label="Mois suivant">›</button>
        </div>

        <div class="cw-legend">
            <div class="cw-legend-item">
                <div class="cw-legend-dot available"></div>
                <span>Disponible</span>
            </div>
            <div class="cw-legend-item">
                <div class="cw-legend-dot selected"></div>
                <span>Sélection</span>
            </div>
            <div class="cw-legend-item">
                <div class="cw-legend-dot unavailable"></div>
                <span>Indisponible</span>
            </div>
        </div>

        <div class="cw-grid" id="cw-grid"></div>

        <!-- Sélecteur de quantité -->
        <div class="cw-quantity-section" id="cw-quantity-section">
            <label class="cw-quantity-label" id="cw-quantity-label">Nombre de semaines</label>
            <div class="cw-quantity-controls">
                <button class="cw-qty-btn" id="cw-qty-minus" aria-label="Moins">−</button>
                <div class="cw-qty-display">
                    <div class="cw-qty-number" id="cw-qty-number">1</div>
                    <div class="cw-qty-unit" id="cw-qty-unit">semaine</div>
                </div>
                <button class="cw-qty-btn" id="cw-qty-plus" aria-label="Plus">+</button>
            </div>
        </div>

        <!-- Résumé -->
        <div class="cw-summary" id="cw-summary">
            <div class="cw-summary-row">
                <span class="cw-summary-label">Dates</span>
                <span class="cw-summary-value" id="cw-dates-display">-</span>
            </div>
            <div class="cw-summary-row">
                <span class="cw-summary-label">Durée</span>
                <span class="cw-summary-value" id="cw-duration-display">-</span>
            </div>
            <div class="cw-summary-total">
                <span class="cw-summary-total-label">Total</span>
                <span class="cw-summary-total-price" id="cw-price-display">-</span>
            </div>
            <button class="cw-reserve-btn" id="cw-book-btn">Réserver maintenant</button>
        </div>
    </div>
</div>

<div id="cw-toast" class="cw-toast"></div>

<script>
(function() {
    const API_URL = window.location.origin + '/wp-json/coworking/v1';

    const CONFIG = {
        offreId: document.getElementById('cw-container')?.dataset.offreId,
        monthsFr: ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'],
        daysFr: ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'],
        todayIso: new Date().toISOString().split('T')[0],
        blocDays: {
            journee: 1,
            semaine: 7,
            mois: 30
        },
        unitLabels: {
            journee: { singular: 'jour', plural: 'jours' },
            semaine: { singular: 'semaine', plural: 'semaines' },
            mois: { singular: 'mois', plural: 'mois' }
        }
    };

    const STATE = {
        currentMonth: new Date(),
        formule: null,
        startDate: null,
        quantity: 1,
        cache: {},
        prices: {
            journee: <?php echo $price_day ?: 0; ?>,
            semaine: <?php echo $price_week ?: 0; ?>,
            mois: <?php echo $price_month ?: 0; ?>
        }
    };

    // Utilitaires dates
    const DateUtils = {
        format: (d) => d.toISOString().split('T')[0],

        addDays: (isoStr, days) => {
            const parts = isoStr.split('-').map(Number);
            const d = new Date(parts[0], parts[1] - 1, parts[2]);
            d.setDate(d.getDate() + days);
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },

        toMonthKey: (dateObj) => {
            return `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}`;
        },

        formatHuman: (isoStr) => {
            if (!isoStr) return '';
            const parts = isoStr.split('-');
            const d = new Date(parts[0], parts[1] - 1, parts[2]);
            return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
        },

        formatShort: (isoStr) => {
            const parts = isoStr.split('-');
            const d = new Date(parts[0], parts[1] - 1, parts[2]);
            return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        }
    };

    // Calcul de la date de fin
    function calculateEndDate(startDate, formule, quantity) {
        const blocDays = CONFIG.blocDays[formule] || 1;
        const totalDays = blocDays * quantity;
        return DateUtils.addDays(startDate, totalDays - 1);
    }

    // Initialisation du calendrier
    async function initCalendar(formuleType) {
        if (!CONFIG.offreId) return;

        STATE.formule = formuleType;
        STATE.startDate = null;
        STATE.quantity = 1;

        // UI updates
        const container = document.getElementById('cw-container');
        container.classList.add('active');

        document.getElementById('cw-formule-badge').textContent = formuleType.charAt(0).toUpperCase() + formuleType.slice(1);

        updateQuantitySection();
        updateSummary(false);

        await renderMonth(STATE.currentMonth);

        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Mise à jour section quantité
    function updateQuantitySection() {
        const section = document.getElementById('cw-quantity-section');
        const label = document.getElementById('cw-quantity-label');
        const qtyNumber = document.getElementById('cw-qty-number');
        const qtyUnit = document.getElementById('cw-qty-unit');
        const minusBtn = document.getElementById('cw-qty-minus');

        if (!STATE.startDate) {
            section.classList.remove('active');
            return;
        }

        section.classList.add('active');

        const unitLabel = CONFIG.unitLabels[STATE.formule];
        label.textContent = `Nombre de ${unitLabel.plural}`;
        qtyNumber.textContent = STATE.quantity;
        qtyUnit.textContent = STATE.quantity > 1 ? unitLabel.plural : unitLabel.singular;

        minusBtn.disabled = STATE.quantity <= 1;
    }

    // Rendu du mois
    async function renderMonth(dateObj) {
        const monthKey = DateUtils.toMonthKey(dateObj);

        document.getElementById('cw-month-label').textContent =
            `${CONFIG.monthsFr[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

        const grid = document.getElementById('cw-grid');

        if (!STATE.cache[monthKey]) {
            grid.innerHTML = '<div class="cw-loading">Chargement...</div>';
            try {
                const res = await fetch(`${API_URL}/availability/${CONFIG.offreId}?month=${monthKey}`);
                const data = await res.json();
                if (data.success) {
                    STATE.cache[monthKey] = data.availability;
                }
            } catch (e) {
                console.error("API Error", e);
                showToast("Erreur de connexion", "error");
                return;
            }
        }

        buildGrid(dateObj, STATE.cache[monthKey]);
    }

    // Construction de la grille
    function buildGrid(dateObj, availabilityData) {
        const grid = document.getElementById('cw-grid');
        grid.innerHTML = '';

        // Headers jours
        CONFIG.daysFr.forEach(d => {
            const el = document.createElement('div');
            el.className = 'cw-day-header';
            el.textContent = d;
            grid.appendChild(el);
        });

        const year = dateObj.getFullYear();
        const month = dateObj.getMonth();

        const firstDay = new Date(year, month, 1).getDay();
        const blanks = (firstDay === 0 ? 6 : firstDay - 1);

        for (let i = 0; i < blanks; i++) {
            grid.appendChild(document.createElement('div'));
        }

        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let d = 1; d <= daysInMonth; d++) {
            const dayStr = String(d).padStart(2, '0');
            const iso = `${year}-${String(month + 1).padStart(2, '0')}-${dayStr}`;
            const info = availabilityData[iso] || { status: 'unavailable' };

            const btn = document.createElement('div');
            btn.className = 'cw-day';
            btn.textContent = d;
            btn.dataset.date = iso;

            if (info.status === 'unavailable' || info.status === 'full' || info.is_past) {
                btn.classList.add('unavailable');
                if (info.status === 'full') btn.classList.add('full');
                if (info.is_past) btn.classList.add('past');
            } else {
                btn.classList.add('available');
                if (info.status === 'low') btn.classList.add('low');
            }

            applySelectionClasses(btn, iso);
            grid.appendChild(btn);
        }
    }

    // Application des classes de sélection
    function applySelectionClasses(el, iso) {
        if (!STATE.startDate) return;

        const endDate = calculateEndDate(STATE.startDate, STATE.formule, STATE.quantity);

        if (iso === STATE.startDate) {
            el.classList.add('selected', 'range-start');
        } else if (iso === endDate) {
            el.classList.add('selected', 'range-end');
        } else if (iso > STATE.startDate && iso < endDate) {
            el.classList.add('in-range');
        }
    }

    // Rafraîchir les classes de la grille
    function refreshGridClasses() {
        const cells = document.querySelectorAll('.cw-day');
        cells.forEach(cell => {
            cell.classList.remove('selected', 'in-range', 'range-start', 'range-end');
            const iso = cell.dataset.date;
            if (iso) applySelectionClasses(cell, iso);
        });
    }

    // Vérification disponibilité de la plage
    async function checkAvailabilityRange(startIso, endIso) {
        // Collecter tous les mois nécessaires
        const monthsToCheck = new Set();
        let curr = startIso;

        while (curr <= endIso) {
            monthsToCheck.add(curr.substring(0, 7));
            curr = DateUtils.addDays(curr, 15);
        }
        monthsToCheck.add(endIso.substring(0, 7));

        // Charger les mois manquants
        const promises = [];
        for (const mKey of monthsToCheck) {
            if (!STATE.cache[mKey]) {
                promises.push(
                    fetch(`${API_URL}/availability/${CONFIG.offreId}?month=${mKey}`)
                        .then(r => r.json())
                        .then(d => { if(d.success) STATE.cache[mKey] = d.availability; })
                );
            }
        }
        await Promise.all(promises);

        // Vérifier chaque jour
        let d = startIso;
        while (d <= endIso) {
            const mKey = d.substring(0, 7);
            const data = STATE.cache[mKey];

            if (!data || !data[d]) return { valid: false, failDate: d };
            if (data[d].status === 'unavailable' || data[d].status === 'full' || data[d].slots <= 0) {
                return { valid: false, failDate: d };
            }
            d = DateUtils.addDays(d, 1);
        }

        return { valid: true };
    }

    // Mise à jour de la sélection
    async function updateSelection() {
        if (!STATE.startDate) return;

        const endDate = calculateEndDate(STATE.startDate, STATE.formule, STATE.quantity);

        refreshGridClasses();

        const check = await checkAvailabilityRange(STATE.startDate, endDate);

        if (!check.valid) {
            showToast(`Date indisponible : ${DateUtils.formatShort(check.failDate)}`, "error");

            // Réduire la quantité jusqu'à trouver une plage valide
            while (STATE.quantity > 1) {
                STATE.quantity--;
                const newEnd = calculateEndDate(STATE.startDate, STATE.formule, STATE.quantity);
                const recheck = await checkAvailabilityRange(STATE.startDate, newEnd);
                if (recheck.valid) {
                    updateQuantitySection();
                    refreshGridClasses();
                    break;
                }
            }

            if (STATE.quantity === 1) {
                const finalCheck = await checkAvailabilityRange(STATE.startDate, calculateEndDate(STATE.startDate, STATE.formule, 1));
                if (!finalCheck.valid) {
                    STATE.startDate = null;
                    STATE.quantity = 1;
                    updateQuantitySection();
                    refreshGridClasses();
                    updateSummary(false);
                    return;
                }
            }
        }

        updateSummary(true);
    }

    // Mise à jour du résumé
    function updateSummary(show) {
        const summary = document.getElementById('cw-summary');

        if (!show || !STATE.startDate) {
            summary.classList.remove('active');
            return;
        }

        summary.classList.add('active');

        const endDate = calculateEndDate(STATE.startDate, STATE.formule, STATE.quantity);
        const unitPrice = STATE.prices[STATE.formule];
        const totalPrice = unitPrice * STATE.quantity;

        // Dates
        const datesDisplay = document.getElementById('cw-dates-display');
        if (STATE.startDate === endDate) {
            datesDisplay.textContent = DateUtils.formatShort(STATE.startDate);
        } else {
            datesDisplay.textContent = `${DateUtils.formatShort(STATE.startDate)} → ${DateUtils.formatShort(endDate)}`;
        }

        // Durée
        const durationDisplay = document.getElementById('cw-duration-display');
        const unitLabel = CONFIG.unitLabels[STATE.formule];
        durationDisplay.textContent = `${STATE.quantity} ${STATE.quantity > 1 ? unitLabel.plural : unitLabel.singular}`;

        // Prix
        document.getElementById('cw-price-display').textContent = `${totalPrice}€`;
    }

    // Clic sur grille
    document.getElementById('cw-grid').addEventListener('click', async (e) => {
        const cell = e.target.closest('.cw-day');
        if (!cell || cell.classList.contains('unavailable')) return;

        const clickedDate = cell.dataset.date;

        // Reset quantité et définir nouvelle date de début
        STATE.startDate = clickedDate;
        STATE.quantity = 1;

        updateQuantitySection();
        await updateSelection();
    });

    // Boutons quantité
    document.getElementById('cw-qty-minus').addEventListener('click', async () => {
        if (STATE.quantity > 1) {
            STATE.quantity--;
            updateQuantitySection();
            await updateSelection();
        }
    });

    document.getElementById('cw-qty-plus').addEventListener('click', async () => {
        STATE.quantity++;
        updateQuantitySection();
        await updateSelection();
    });

    // Navigation mois
    document.getElementById('cw-prev').addEventListener('click', () => {
        STATE.currentMonth.setMonth(STATE.currentMonth.getMonth() - 1);
        renderMonth(STATE.currentMonth);
    });

    document.getElementById('cw-next').addEventListener('click', () => {
        STATE.currentMonth.setMonth(STATE.currentMonth.getMonth() + 1);
        renderMonth(STATE.currentMonth);
    });

    // Bouton réserver
    document.getElementById('cw-book-btn').addEventListener('click', async () => {
        if (!STATE.startDate) return;

        const btn = document.getElementById('cw-book-btn');
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Validation...';

        const endDate = calculateEndDate(STATE.startDate, STATE.formule, STATE.quantity);

        try {
            const res = await fetch(`${API_URL}/cart-add`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    offre_id: CONFIG.offreId,
                    formule: STATE.formule,
                    start: STATE.startDate,
                    end: endDate,
                    quantity: STATE.quantity
                })
            });

            const json = await res.json();

            if (json.success) {
                showToast("Réservation validée ! Redirection...", "success");
                btn.textContent = "Redirection...";
                window.location.href = json.cart_url;
            } else {
                throw new Error(json.message);
            }

        } catch (e) {
            console.error("Booking Error", e);
            showToast(e.message || "Erreur lors de la réservation", "error");
            btn.disabled = false;
            btn.textContent = originalText;

            // Rafraîchir si problème de dispo
            if (e.message && (e.message.includes('disponible') || e.message.includes('date'))) {
                STATE.cache = {};
                renderMonth(STATE.currentMonth);
            }
        }
    });

    // Toast notifications
    function showToast(msg, type = 'info') {
        const t = document.getElementById('cw-toast');
        t.textContent = msg;
        t.className = `cw-toast show ${type}`;
        setTimeout(() => t.classList.remove('show'), 3500);
    }

    // Export global
    window.initCoworkingCalendar = initCalendar;

    // Auto-init sur boutons formule
    document.addEventListener('DOMContentLoaded', () => {
        const triggers = {
            'formule-journee': 'journee',
            'formule-semaine': 'semaine',
            'formule-mois': 'mois'
        };

        Object.keys(triggers).forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.style.cursor = 'pointer';
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.querySelectorAll('.formule-active').forEach(b => b.classList.remove('formule-active'));
                    btn.classList.add('formule-active');
                    initCalendar(triggers[id]);
                });
            }
        });
    });

})();
</script>
    <?php
    return ob_get_clean();
}
