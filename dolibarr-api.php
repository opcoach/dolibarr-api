<?php
/**
 * Plugin Name: Dolibarr API
 * Description: Fournit des classes et fonctions pour accéder à l'API Dolibarr (commandes, factures...).
 * Version: 1.0.0
 * Author: Olivier
 */

defined('ABSPATH') || exit;

// Chargement automatique des classes
require_once plugin_dir_path(__FILE__) . 'DolibarrObject.php';
require_once plugin_dir_path(__FILE__) . 'DolibarrInvoice.php';
require_once plugin_dir_path(__FILE__) . 'DolibarrProposal.php';
require_once plugin_dir_path(__FILE__) . 'DolibarrShortCode.php';