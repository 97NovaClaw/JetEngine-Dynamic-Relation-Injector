<?php
/**
 * Main Plugin Class
 *
 * Singleton that orchestrates all modules
 *
 * @package JetRelationInjector
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main plugin singleton class
 */
class Jet_Injector_Plugin {
    
    /**
     * Plugin instance
     *
     * @var Jet_Injector_Plugin
     */
    private static $instance = null;
    
    /**
     * Discovery Engine instance
     *
     * @var Jet_Injector_Discovery
     */
    public $discovery;
    
    /**
     * Configuration Manager instance
     *
     * @var Jet_Injector_Config_Manager
     */
    public $config_manager;
    
    /**
     * Transaction Processor instance
     *
     * @var Jet_Injector_Transaction_Processor
     */
    public $transaction_processor;
    
    /**
     * Data Broker instance
     *
     * @var Jet_Injector_Data_Broker
     */
    public $data_broker;
    
    /**
     * Runtime Loader instance
     *
     * @var Jet_Injector_Runtime_Loader
     */
    public $runtime_loader;
    
    /**
     * Admin Page instance
     *
     * @var Jet_Injector_Admin_Page
     */
    public $admin_page;
    
    /**
     * Get singleton instance
     *
     * @return Jet_Injector_Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize all modules
     */
    private function __construct() {
        $this->init_modules();
        
        jet_injector_debug_log('Plugin modules initialized');
    }
    
    /**
     * Initialize plugin modules
     */
    private function init_modules() {
        // Module A: Discovery Engine
        $this->discovery = new Jet_Injector_Discovery();
        
        // Module B: Configuration Manager
        $this->config_manager = new Jet_Injector_Config_Manager();
        
        // Module D: Transaction Processor
        $this->transaction_processor = new Jet_Injector_Transaction_Processor();
        
        // Module E: Data Broker
        $this->data_broker = new Jet_Injector_Data_Broker();
        
        // Module C: Runtime Loader
        $this->runtime_loader = new Jet_Injector_Runtime_Loader();
        
        // Admin Page
        if (is_admin()) {
            $this->admin_page = new Jet_Injector_Admin_Page();
            $this->utilities = new Jet_Injector_Utilities();
        }
    }
    
    /**
     * Get Discovery Engine instance
     *
     * @return Jet_Injector_Discovery
     */
    public function get_discovery() {
        return $this->discovery;
    }
    
    /**
     * Get Config Manager instance
     *
     * @return Jet_Injector_Config_Manager
     */
    public function get_config_manager() {
        return $this->config_manager;
    }
    
    /**
     * Get Transaction Processor instance
     *
     * @return Jet_Injector_Transaction_Processor
     */
    public function get_transaction_processor() {
        return $this->transaction_processor;
    }
    
    /**
     * Get Data Broker instance
     *
     * @return Jet_Injector_Data_Broker
     */
    public function get_data_broker() {
        return $this->data_broker;
    }
    
    /**
     * Get Runtime Loader instance
     *
     * @return Jet_Injector_Runtime_Loader
     */
    public function get_runtime_loader() {
        return $this->runtime_loader;
    }
    
    /**
     * Get Admin Page instance
     *
     * @return Jet_Injector_Admin_Page|null
     */
    public function get_admin_page() {
        return $this->admin_page;
    }
}

