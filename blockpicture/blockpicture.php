<?php
/**
 *
 * @author RObin Guzniczak <robin.guzniczak@gmail.com>
 */

if (!defined('_PS_VERSION_'))
    exit;

class BlockPicture extends Module
{
    public function __construct()
    {
        /* Settings */
        $this->name = 'blockpicture';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Robin Guzniczak';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Block picture');
        $this->description = $this->l('Adds a block containing an image');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        if (!Configuration::get('BLOCKPICTURE_NAME'))
            $this->warning = $this->l('No name provided');
    }

    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        /* No image by default */
        if (!parent::install() ||
          !$this->registerHook('leftColumn') ||
          !$this->registerHook('header') ||
          !Configuration::updateValue('BLOCKPICTURE_URL', '') ||
          !Configuration::updateValue('BLOCKPICTURE_UPLOADED', false))
            return false;
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
         !Configuration::deleteByName('BLOCKPICTURE_URL') ||
         !Configuration::deleteByName('BLOCKPICTURE_UPLOADED'))
            return false;
        return true;
    }

    public function hookDisplayLeftColumn($params)
    {
        /* Get the actual URL of the image if it is on the server */
        $url = Configuration::get('BLOCKPICTURE_URL');
        if (Configuration::get('BLOCKPICTURE_UPLOADED'))
            $url = _MODULE_DIR_ . 'blockpicture/images/' . $url;
        $this->context->smarty->assign(array('blockpicture_url' => $url));
        return $this->display(__FILE__, 'views/templates/hook/blockpicture.tpl');
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name))
        {
            $valid_image = true;
            /* Handle direct URL */
        if (!isset($_FILES['BLOCKPICTURE_FILE']) ||
          !isset($_FILES['BLOCKPICTURE_FILE']['tmp_name']) ||
          empty($_FILES['BLOCKPICTURE_FILE']['tmp_name'])) {
                $url = strval(Tools::getValue('BLOCKPICTURE_URL'));
                if (!$url || empty($url))
                    $output .= $this->displayError($this->l('Invalid Url'));
                /* It could also be interesting to check that the url points to a valid file */
                else
                {
                    Configuration::updateValue('BLOCKPICTURE_URL', $url);
                    Configuration::updateValue('BLOCKPICTURE_UPLOADED', false);
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
            }
            /* Upload file */
            else
                {
                $image_dir = dirname(__FILE__) . '/images/';
                $temp_name = image_dir . $_FILES['BLOCKPICTURE_FILE']['name'];
                $salt = sha1(microtime()); /* not sure this is really necessary */
                $target_name = $salt . '_' . $_FILES['BLOCKPICTURE_FILE']['name'];
                $file_type = Tools::strtolower(pathinfo($temp_name, PATHINFO_EXTENSION));
                $imagesize = getimagesize($_FILES['BLOCKPICTURE_FILE']['tmp_name']);
                /* Check validity of image */
                if ($imagesize === false)
                    $output .= $this->displayError($this->l('Invalid image'));
                elseif (!in_array($file_type, array('jpg', 'jpeg', 'gif', 'png', 'svg')))
                    $output .= $this->displayError($this->l('Only png, jpg, jpeg, gif and svg files are allowed'));
                elseif ($error = ImageManager::validateUpload($_FILES['BLOCKPICTURE_FILE']))
                    $output .= $this->displayError($error);
                elseif (!$temp_name || !move_uploaded_file($_FILES['BLOCKPICTURE_FILE']['tmp_name'], $temp_name))
                    $output .= $this->displayError('Failed to upload file');
                elseif (!ImageManager::resize($temp_name, $image_dir . $target_name, null, null, $type))
                    $output .= $this->displayError('Failed to upload fileee');
                else
                {
                    /* Update settings */
                    Configuration::updateValue('BLOCKPICTURE_URL', $target_name);
                    Configuration::updateValue('BLOCKPICTURE_UPLOADED', true);
                    $output .= $this->displayConfirmation($this->l('Settings updated and image uploaded'));
                }
            }
        }
        return $output . $this->displayForm();  
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        /* Form */
        $fields_form[0]['form'] = array(
            'legend' => array('title' => $this->l('Settings')),
            'input' => array(
                array(
                    'type' => 'file',
                    'label' => $this->l('Image'),
                    'name' => 'BLOCKPICTURE_FILE',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Image link'),
                    'name' => 'BLOCKPICTURE_URL',
                    'size' => 20,
                    'desc' => 'You can either upload your own image using the online form above or put a link to an existing image using "Image link"'
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        /* Language */
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        /* Title and Toolbar */
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );
        $helper->identifier = $this->identifier;
        $helper->fields_value['BLOCKPICTURE_URL'] = Configuration::get('BLOCKPICTURE_URL');

        return $helper->generateForm($fields_form);
    }
}
