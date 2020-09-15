<?php

class login_otp extends rcube_plugin
{
	private $rcmail;

	const LOGIN_OTP_CHECK = 'login_otp_check';
	const LOGIN_OTP_ERROR = 'login_otp_error';
	const LOGIN_OTP_REQUEST = 'login_otp_request';
	const LOGIN_OTP_VALUE = 'login_otp_value';

	function init()
	{
		$this->rcmail = rcmail::get_instance();
		$this->load_config();
		$this->add_texts('localization/', true);
		$this->add_hook('startup', array($this, 'startup'));
		$this->add_hook('login_after', array($this, 'login_after'));
		
		$this->add_hook('preferences_list', array($this, 'preferences_list'));
		$this->add_hook('preferences_save', array($this, 'preferences_save'));
		
		$this->register_action('plugin.login_otp', array($this, 'otp_form'));
		$this->register_action('plugin.login_otp_register', array($this, 'register_form'));
		$this->register_action('plugin.login_otp_action', array($this, 'login_otp_request_handler'));
		$this->register_action('plugin.login_otp_register_action', array($this, 'login_otp_register_handler'));
	}

	function startup($args)
	{
		if ($_SESSION[self::LOGIN_OTP_CHECK] && $args['action'] != "plugin.login_otp_action") {
			$args['_task'] = null;
			$args['action'] = "plugin.login_otp";
			return $args;
		} else if (!$_SESSION[self::LOGIN_OTP_CHECK] && ($args['action'] == "plugin.login_otp" ||
			$args['action'] == "plugin.login_otp_action")) {
			$this->rcmail->output->redirect(array('_task' => "mail", 'action' => null));
		}
		return $args;
	}

	function send_otp()
	{
                $otp_url = "https://platform.clickatell.com/messages/http/send";
		$otp_phone = $this->get_number();

		if (empty($otp_phone)) {
			return -1;
		}

                $otp_value = sprintf("%06d", mt_rand(1, 999999));
                $_SESSION[self::LOGIN_OTP_VALUE] = $otp_value;

                $content = $this->gettext(array(
	                'name' => 'otp_message',
                        'vars' => array('$otp' => $otp_value)
                ));

                $params = array(
                        "apiKey" => $this->rcmail->config->get('clickatell_api_key'),
                        "to" => $otp_phone,
                        "content" => $content
                );

                $sender_id = $this->rcmail->config->get('clickatell_sender_id');
                if (!empty($sender_id)) {
                        $params["from"] = $sender_id;
                }

                $request_url = $otp_url . "?" . http_build_query($params);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $request_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_exec($ch);
                curl_close($ch);	

		$_SESSION[self::LOGIN_OTP_CHECK] = true;

		return 0;
	}

	function get_number()
	{
            $db = rcube_db::factory($this->rcmail->config->get('db_dsnw'));

            $db->db_connect('r');

            $sql_result = $db->query('SELECT otp_number FROM ' . $db->quote_identifier('rcz6_users')
		    .' WHERE `user_id` = ?', $_SESSION['user_id']);

	    if ($sql_array = $db->fetch_assoc($sql_result) ) {
   		return $sql_array['otp_number']; 
            }
	}

	function register_otp($phoneNumber)
	{
            $db = rcube_db::factory($this->rcmail->config->get('db_dsnw'));

            $db->db_connect('r');

	    $db->query('UPDATE ' . $db->quote_identifier('rcz6_users') . ' SET otp_number = ? WHERE `user_id` = ?',
		    $phoneNumber, $_SESSION['user_id']);
	}

	function login_after($args)
	{
	    if ($this->rcmail->config->get('login_otp_enabled'))
	    {
    		if ($this->send_otp())
	    	{
		    	$args['_task'] = null;
			    $args['action'] = 'plugin.login_otp_register';
		    }
		    else
		    {
			    $args['_task'] = null;
			    $args['action'] = 'plugin.login_otp';
		    }
	    }

		return $args;
	}

	function otp_form()
	{
		$this->register_handler('plugin.body', array($this, 'page_body'));

		if (!empty($_SESSION[self::LOGIN_OTP_ERROR])) {
			$this->register_handler('plugin.message', array($this, 'error_message'));
		}

		$this->rcmail->output->send('login_otp.otp_form');
	}

	function register_form()
	{
		$this->register_handler('plugin.body', array($this, 'register_body'));

                if (!empty($_SESSION[self::LOGIN_OTP_ERROR])) {
                        $this->register_handler('plugin.message', array($this, 'error_message'));
                }

                $this->rcmail->output->send('login_otp.otp_form');
	}

	function error_message()
	{
		$message = $_SESSION[self::LOGIN_OTP_ERROR];
		$_SESSION[self::LOGIN_OTP_ERROR] = null;
		return '<div class="t-alert-container"><div class="t-error"><div class="t-dismiss"></div><div class="t-message-container">' . $message . '</div></div></div>';
	}

	function page_title()
	{
		return $this->gettext('otp_label');
	}

	function page_body()
	{
		$output = '<form id="login-form" name="login-form" method="post" class="propform" action="./?_action=plugin.login_otp_action">';
		$output .= '<table>';
		$output .= '<tbody>';
		$output .= '<tr class="form-group row">';
		$output .= '<td class="title" style="display: none;">';
		$output .= '<label for="rcmloginotp">' . $this->gettext('otp_label') . '</label>';
		$output .= '</td>';
		$output .= '<td class="input input-group input-group-lg">';
		$output .= '<input style="width: 100%" name="_otp" id="rcmloginotp" required="" size="40" type="text" class="form-control" placeholder="' . $this->gettext('otp_label') . '">';
		$output .= '</td>';
		$output .= '</tr>';
		$output .= '</tbody>';
		$output .= '</table>';
		$output .= '<p class="formbuttons">';
		$output .= '<button type="submit" id="rcmloginsubmit" class="button mainaction submit btn btn-primary btn-lg text-uppercase w-100">' . $this->gettext('validate_button') . '</button>';
		$output .= '</p>';
		$output .= '</form>';
		return $output;
	}

        function register_body()
        {
                $output = '<form id="login-form" name="login-form" method="post" class="propform" action="./?_action=plugin.login_otp_register_action">';
                $output .= '<table>';
                $output .= '<tbody>';
		$output .= '<tr class="form-group row">';
                $output .= '<td class="title" style="display: none;">';
                $output .= '<label for="rcmloginphone">' . $this->gettext('phone_label') . '</label>';
                $output .= '</td>';
		$output .= '<td class="input input-group input-group-lg">';
                $output .= '<input style="width: 100%" name="_phone" id="rcmloginphone" required="" size="40" type="text" class="form-control" placeholder="' . $this->gettext('phone_label') . '">';
                $output .= '</td>';
                $output .= '</tr>';
                $output .= '</tbody>';
                $output .= '</table>';
                $output .= '<p class="formbuttons">';
                $output .= '<button type="submit" id="rcmloginsubmit" class="button mainaction submit btn btn-primary btn-lg text-uppercase w-100">' . $this->gettext('submit_button') . '</button>';
                $output .= '</p>';
                $output .= '</form>';
                return $output;
        }

	function login_otp_request_handler($args = array())
	{
		if (strcmp($_POST['_otp'], $_SESSION[self::LOGIN_OTP_VALUE]))
		{
			$_SESSION[self::LOGIN_OTP_ERROR] = $this->gettext('invalid_otp');
			$this->send_otp();
			$this->rcmail->output->redirect(array('_task' => 'login', 'action' => 'plugin.login_otp'));
		}
		else
		{
			$_SESSION[self::LOGIN_OTP_CHECK] = null;
			$_SESSION[self::LOGIN_OTP_REQUEST] = null;
			$this->rcmail->output->redirect(array('_task' => 'mail', 'action' => null));
		}
	}

        function login_otp_register_handler($args = array())
	{
		/* $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

		$phoneNumberObject = $phoneNumberUtil->parse($_POST['_phone'], 'SI'); */
		
		// Format number
		preg_match_all('!\d+!', $_POST['_phone'], $matches);
		$phone = preg_replace('/[^0-9]/', '', $_POST['_phone']);
		$phone = preg_replace('/^386/', '', $phone);
		$phone = preg_replace('/^0/', '', $phone);
		$phone = "386" . $phone;
		
		if (strlen($phone) == 11)
		{
			// $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
			$this->register_otp($phone);
                        $_SESSION[self::LOGIN_OTP_CHECK] = null;
                        $_SESSION[self::LOGIN_OTP_REQUEST] = null;
                        $this->rcmail->output->redirect(array('_task' => 'mail', 'action' => null));
		}
		else
                {
                        $_SESSION[self::LOGIN_OTP_ERROR] = $this->gettext('invalid_phone');
                        $this->rcmail->output->redirect(array('_task' => 'login', 'action' => 'plugin.login_otp_register'));
                }
        }
        
    function preferences_list($args)
	{
		if ($args['section'] == 'server') {
			$otp_enabled = $this->rcmail->config->get('login_otp_enabled', false);
			$field_id = 'login_otp_enabled';
			$checkbox = new html_checkbox(array('name' => '_login_otp_enabled', 'id' => $field_id, 'value' => 1));
			$args['blocks']['login_otp']['name'] = $this->gettext('otp_label');
			$args['blocks']['login_otp']['options']['login_otp_enabled'] = array(
                'title' => html::label($field_id, $this->gettext('otp_enabled')),
                'content' => $checkbox->show($otp_enabled ? 1 : 0)
			);
			$args['blocks']['login_otp']['options']['login_otp_enabled_help'] = array(
						'title' => $this->gettext('otp_description'),
						'content' => '&nbsp;'
					);
		}
		return $args;
	}

	function preferences_save($args)
	{
		if ($args['section'] == 'server') {
			$args['prefs']['login_otp_enabled'] = isset($_POST['_login_otp_enabled']) ? true : false;
			return $args;
		}
	}
}
