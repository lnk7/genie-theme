<?php

use Lnk7\Genie\View;
use Theme\PostTypes\Page;

View::with('index.twig')
	->addVar('page', Page::getCurrent())
	->display();
