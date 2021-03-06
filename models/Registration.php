<?php

class Registration extends Model {
	
	public function validateData($data) {
		if (empty($data['email']))
			return [
				's' => 'error',
				'cs' => 'Prosím vyplň svůj přihlašovací email',
				'en' => 'Please enter your login email'
			];
		if (empty($data['tariff'])) {
			$this->newTicket('error', 'function validateData in Registration', '\$_POST[place] is empty');
			
			return [
				's' => 'error',
				'cs' => 'Nepodařilo se zachytit vybraný tarif. Zkus to prosím znovu',
				'en' => 'Unable to capture the selected tariff. Please try again.'
			];
		}
		if (empty($data['firstname']))
			return [
				's' => 'error',
				'cs' => 'Prosím vyplň křestní jméno',
				'en' => 'Please fill in your first name'
			];
		if (empty($data['surname']))
			return [
				's' => 'error',
				'cs' => 'Prosím vyplň příjmení',
				'en' => 'Please enter your surname'
			];
		if (!empty($data['telephone']) && !preg_match('/^\+?[\d ]+$/', $data['telephone']))
			return [
				's' => 'error',
				'cs' => 'Telefoní číslo musí být číslo (volitelně i s národní předvolbou)',
				'en' => 'Telephone number must be a number (optionally with country prefix)'
			];
		if (!empty($data['ic']))
			if (!is_numeric($data['ic']))
				return [
					's' => 'error',
					'cs' => 'IČ musí být číslo',
					'en' => 'VAT must be a number'
				];
		if (strlen($data['address']) > 120)
			return [
				's' => 'error',
				'cs' => 'Adresa by měla být dlouhá max. 120 znaků',
				'en' => 'Address should be long maximum of 120 characters'
			];
		if ($data['startDate'] != date('Y-m-d', strtotime($data['startDate']))) {
			$this->newTicket('error', 'function validateData in Registration', '\$_POST[startDate] is in bad format');
			
			return [
				's' => 'error',
				'cs' => 'Špatný formát zadání prvního dne',
				'en' => 'Bad format of starting date'
			];
		}
		if ($data['tariff'] == 'X')
			return [
				's' => 'error',
				//non-choosed tariff
				'cs' => 'Prosím vyber svůj tarif',
				'en' => 'Please select your tariff'
			];
		if (strlen($data['p']) != 128) {
			$this->newTicket('error', 'function validateData in Registration', 'Something wrong with \'p\' in registration; p='.$_POST['p'].',strlen($p)='.strlen($data['p']));
			
			return [
				's' => 'error',
				'cs' => 'Nepovedlo se správně zachytit heslo - zkus to prosím znovu',
				'en' => 'Failed to properly capture the password - please try again'
			];
		}
		
		$attempt = Db::queryOne('SELECT `id_user`,`email`,`password`,`salt` FROM `users`
                                 WHERE `email` = ?', [$data['email']]);
		//if in DB is found that email
		if ($attempt[0] != null)
			return [
				's' => 'error',
				'cs' => 'Tento email už registrovaný je. <a href="'.ROOT.'/cs/login">Přihlásit se?</a>',
				'en' => 'This email has already been registred. <a href="'.ROOT.'/en/login">Log in?</a>'
			];
		
		//success
		return ['s' => 'success'];
	}
	
	public function registerUser($data, $language) {
		$randomSalt = $this->getRandomHash();
		$saltedPassword = hash('sha512', $data['p'].$randomSalt);
		$databaseData = [
			$data['firstname'],
			$data['surname'],
			$data['tariff'],
			$data['email'],
			$saltedPassword,
			$randomSalt,
			$data['startDate'],
			$data['telephone'],
			$data['address'],
			$data['ic'],
			$data['company'],
			$data['fakturoid_id'],
		];
		
		//insert user into DB
		if (!Db::queryModify('INSERT INTO `users` (`first_name`,`last_name`,`user_tariff`,`active`,`email`,`password`,`salt`,`invoicing_start_date`,`telephone`,`address`,`ic`,`company`,`fakturoid_id`)
                              VALUES (?,?,?,0,?,?,?,?,?,?,?,?,?)', $databaseData)
		)
			return [
				's' => 'error',
				'cs' => 'Nepovedlo se zapsat do databáze. Zkuste to prosím později',
				'en' => 'Unable to write to the database . Please try again later'
			];
		
		//generate activation link...
		$randomHash = $this->getRandomHash();
		if (!Db::queryModify('INSERT INTO `activation`(`validation_string`,`email`,`active`,`timestamp`)
                              VALUES (?,?,1,NOW())', [$randomHash, $data["email"]])
		)
			return [
				's' => 'error',
				'cs' => 'Nepovedlo se zapsat do databáze. Zkuste to prosím později',
				'en' => 'Unable to write to the database . Please try again later'
			];
		
		//...and send activation link
		$subject = [
			'cs' => NAME.' Paralelní Polis - aktivace nového účtu',
			'en' => NAME.' Paralell Polis - activation of new account',
		];
		
		$activeLink = ROOT.'/'.$language.'/activation/'.$randomHash;
		
		$message = [
			'cs' => 'Zdravím!<br/>
<br/>
Klikem na tento odkaz si aktivuješ účet v systému '.NAME.' z Paralelní polis: <br/>
<a href="'.$activeLink.'">'.$activeLink.'</a><br/>
<br/>
Pokud tento email neočekáváš, stačí ho ignorovat. <br/>',
			'en' => 'Hi!<br/>
<br/>
Click on this link will activate your account in system '.NAME.' from Paralell polis: <br/>
<a href="'.$activeLink.'">'.$activeLink.'</a><br/>
<br/>
If you don\'t recognize this email, please just ignore it. <br/>'
		];
		
		$this->sendEmail(EMAIL, $data['email'], $subject[$language], $message[$language]);
		
		return [
			's' => 'success',
			'cs' => 'Děkujeme za registraci!</br>Poslali jsme ti email, kde nalezneš odkaz, kterým svou registraci aktivuješ',
			'en' => 'Thanks for registering!</br>We have sent you an email, where you can find a link to activate your account'
		];
	}
}