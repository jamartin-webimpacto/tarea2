<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Productimport extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'productimport';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'jamartin';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Importar CSV');
        $this->description = $this->l('Module for import products from CVS file');

        $this->confirmUninstall = $this->l('Are you sure to uninstall this module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayDashboardToolbarIcons');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitProductimportModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProductimportModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Import new products from CSV file'),
                'icon' => 'icon-cogs',
                'enctype' => "multipart/form-data",
                ),
                'input' => array(
                    array(
                        'col' => 7,
                        'type' => 'file',
                        'desc' => $this->l('Enter a valid csv file'),
                        'name' => 'PRODUCTIMPORT_CSV',
                        'label' => $this->l('Import .csv file'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'PRODUCTIMPORT_CSV' => Configuration::get('PRODUCTIMPORT_CSV'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
                
        $csv =  Tools::getValue('PRODUCTIMPORT_CSV');
     
        $fp = fopen($csv,"r");
        while ($data[] = fgetcsv ($fp, 1000, ",")) {
        }
        $numProducts = count($data);
        fclose($fp);
        
        for($i=1; $i<$numProducts;$i++){
            $product = new Product();
            $product->name = [$default_lang => $data[$i][0]];
            $product->reference = $data[$i][1];
            $product->ean13 = $data[$i][2];
            $product->wholesale_price = $data[$i][3];
            $product->price = $data[$i][4];
            $impuestos = floatval($data[$i][5].'000');
            $product->id_tax_rules_group = $this->getIdTax($impuestos);
            $product->quantity = $data[$i][6];
            $categories = explode(';',$data[$i][7]);
            $i = 0;
            $idCategories = [];
            foreach($categories as $category){
                $idCategory = $this->getIdProductCategory($category, $default_lang);
                if($idCategory){
                    if($i == 0){
                        $product->id_category_default = $idCategory;
                    }
                    $idCategories[]=$idCategory;  
                    $i++;
                }
            }
            if(count($idCategories)>0){
                $product->categories = $data[$i][7];
            }
            $product->id_manufacturer = $this->getIdMarcaProducto($data[$i][8]);   
            
            $product->update();
        }
    }
    
    /**
     * Conseguir el grupo de id_tax al que pertenece un impuesto
     */
    public function getIdTax($tax){
        $db = \Db::getInstance();
        $request = "SELECT id_tax"
                . "FROM ps_tax"
                . "WHERE rate = $tax ";
        $id_tax = $db->getValue($request);
        return $id_tax;  
    }
    
    /**
     * Obtener marca o instanciar una nueva si no existe
     */
    public function getIdMarcaProducto($nombreMarca){
        if($id = \Manufacturer::getIdByName($nombreMarca)){
            return $id;
        }
        else{
           $db = \Db::getInstance();
           $result =  $db->insert('ps_manufacturer',array(
               'name' => $nombreMarca,
               'date_add' => date('Y-m-d H:i:s'),
           ));
           $this->getIdMarcaProducto($nombreMarca);
        }  
    }
    
    /**
     * Obtener categoría si existe y sino la crea y devuelve el id
     */
    public function getIdProductCategory($name,$lang){
        $result = \Category::searchByName($lang, $name);
        if(count($result)>0){
            return $result['id_category'];
        }
        else{
            $newCategory = new \Category();
            $newCategory->add();
            $id = $newCategory->id;
            
            $db = \Db::getInstance();
            $db->insert('ps_category_lang', array(
                'id_category' => $id,
                'id_lang' => $lang,
                'name' => $name,
                'date_upd' => date('Y-m-d H:i:s'),
            ));
            return $id;
        }   
    }
    
    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookDisplayAdminOrderContentOrder()
    {
        /* Place your code here. */
    }
}
