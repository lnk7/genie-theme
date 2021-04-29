<?php

namespace Theme\Templates;

use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\RegisterAjax;

class ContactForm implements GenieComponent
{

	public static function setup()
	{

		RegisterAjax::url('contact-form')
			->run(function (string $email, string $name, string $message) {
				// Do something
			});

	}


}
