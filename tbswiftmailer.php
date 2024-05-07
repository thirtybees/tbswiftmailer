<?php
/**
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class TbSwiftMailer extends Module
{
    const MAIL_METHOD_MAIL = 1;
    const MAIL_METHOD_SMTP = 2;
    const MAIL_METHOD_NONE = 3;

    const SUBMIT = 'tbswiftmailer_submit';

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'tbswiftmailer';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->controllers = [];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Mail via swiftmailer');
        $this->description = $this->l('This module implements mail functionality using swiftmailer library');
        $this->need_instance = 0;
        $this->tb_versions_compliancy = '> 1.4.0';
        $this->tb_min_version = '1.5.0';
    }

    /**
     * Module installation process
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('actionRegisterMailTransport')
        );
    }

    /**
     * @param array $params
     *
     * @return Thirtybees\Core\Mail\MailTransport
     */
    public function hookActionRegisterMailTransport($params)
    {
        require_once(__DIR__ . '/vendor/autoload.php');
        return new TbSwitftMailerModule\SwiftMailerTransport();
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        if (Tools::isSubmit(static::SUBMIT)) {
            $this->updateOptions();
        }
        $helper = new HelperOptions();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $hasPassword = !!Configuration::get('PS_MAIL_PASSWD');

        return $helper->generateOptions([
            'general' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                'fields' => [
                    'PS_MAIL_METHOD'        => [
                        'title'      => '',
                        'validation' => 'isGenericName',
                        'type'       => 'radio',
                        'required'   => true,
                        'choices'    => [
                            static::MAIL_METHOD_MAIL => $this->l('Use PHP\'s mail() function'),
                            static::MAIL_METHOD_SMTP => $this->l('Set my own SMTP parameters (for advanced users ONLY)'),
                            static::MAIL_METHOD_NONE => $this->l('Never send emails (may be useful for testing purposes)'),
                        ],
                    ],
                ],
                'submit' => [
                    'name' => static::SUBMIT,
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ],
            ],
            'smtp' => [
                'title' => $this->l('SMTP settings'),
                'icon' => 'icon-cogs',
                'fields' => [
                    'PS_MAIL_DOMAIN' => [
                        'title' => $this->l('Mail domain name'),
                        'hint' => $this->l('Fully qualified domain name (keep this field empty if you don\'t know).'),
                        'empty' => true, 'validation' =>
                            'isUrl',
                        'type' => 'text',
                    ],
                    'PS_MAIL_SERVER' => [
                        'title' => $this->l('SMTP server'),
                        'hint' => $this->l('IP address or server name (e.g. smtp.mydomain.com).'),
                        'validation' => 'isGenericName',
                        'type' => 'text',
                    ],
                    'PS_MAIL_USER' => [
                        'title' => $this->l('SMTP username'),
                        'hint' => $this->l('Leave blank if not applicable.'),
                        'validation' => 'isGenericName',
                        'type' => 'text',
                    ],
                    'PS_MAIL_PASSWD' => [
                        'title' => $this->l('SMTP password'),
                        'validation' => 'isAnything',
                        'type' => 'password',
                        'autocomplete' => false,
                        'placeholder' => $hasPassword ? $this->l('Use saved password') : null,
                        'hint' => $hasPassword
                            ? $this->l('Leave this field empty to keep using saved password')
                            : $this->l('Leave blank if not applicable.')
                    ],
                    'PS_MAIL_SMTP_ENCRYPTION' => [
                        'title' => $this->l('Encryption'),
                        'hint' => $this->l('Use an encrypt protocol'),
                        'type' => 'select',
                        'cast' => 'strval',
                        'identifier' => 'mode',
                        'list' => [
                            [
                                'mode' => 'off',
                                'name' => $this->l('None'),
                            ],
                            [
                                'mode' => 'tls',
                                'name' => $this->l('TLS'),
                            ],
                            [
                                'mode' => 'ssl',
                                'name' => $this->l('SSL'),
                            ],
                        ],
                    ],
                    'PS_MAIL_SMTP_PORT' => [
                        'title' => $this->l('Port'),
                        'hint' => $this->l('Port number to use.'),
                        'validation' => 'isInt',
                        'type' => 'text',
                        'cast' => 'intval',
                        'class' => 'fixed-width-sm',
                    ],
                ],
                'submit' => [
                    'name' => static::SUBMIT,
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ],
            ]
        ]) . $this->display(__FILE__, 'views/templates/configuration.tpl');
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    private function updateOptions()
    {
        Configuration::updateValue('PS_MAIL_METHOD', (int)Tools::getValue('PS_MAIL_METHOD'));
        Configuration::updateValue('PS_MAIL_DOMAIN', Tools::getValue('PS_MAIL_DOMAIN'));
        Configuration::updateValue('PS_MAIL_SERVER', Tools::getValue('PS_MAIL_SERVER'));
        Configuration::updateValue('PS_MAIL_USER', Tools::getValue('PS_MAIL_USER'));
        Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', Tools::getValue('PS_MAIL_SMTP_ENCRYPTION'));
        Configuration::updateValue('PS_MAIL_SMTP_PORT', (int)Tools::getValue('PS_MAIL_SMTP_PORT'));

        // update password only when set
        $password = Tools::getValue('PS_MAIL_PASSWD');
        if ($password !== '' && $password !== false) {
            Configuration::updateValue('PS_MAIL_PASSWD', $password);
        }
    }
}
