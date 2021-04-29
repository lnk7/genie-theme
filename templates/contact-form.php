<?php
/*
 * Template name: Contact Form
 */

use Lnk7\Genie\View;
use Theme\PostTypes\Page;

View::with('templates\contact-form.twig')
	->addVar('page', Page::getCurrent())
	->display();
