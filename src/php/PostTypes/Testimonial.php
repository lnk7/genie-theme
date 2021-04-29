<?php

namespace Theme\PostTypes;

use Exception;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\RegisterAjax;
use Lnk7\Genie\Utilities\RegisterApi;
use Lnk7\Genie\Utilities\Where;
use Theme\Exceptions\ThemeException;

/**
 * Class Testimonial
 *
 * @package GeniePlugin\PostTypes
 * @property string $name
 * @property string $location
 */
class Testimonial extends CustomPost
{


	static $postType = 'testimonial';


	protected static $locations = [
		'gb' => 'London',
		'fr' => 'France',
	];


	public static function setup()
	{
		parent::setup();

		CreateCustomPostType::called(static::$postType)
			->icon('dashicons-admin-comments')
			->register();

		CreateSchema::called('Testimonial')
			->withFields([
				TextField::called('name')
					->required(true)
					->wrapperWidth(50),
				SelectField::called('location')
					->choices(static::$locations)
					->default('london')
					->returnFormat('value')
					->required(true)
					->wrapperWidth(50),

			])
			->shown(Where::field('post_type')->equals(static::$postType))
			->attachTo(static::class)
			->register();

		RegisterAjax::url('testimonial/create')
			->run([static::class, 'addTestimonial']);

		RegisterApi::post('testimonial/create')
			->run([static::class, 'addTestimonial']);

		RegisterApi::get('testimonials')
			->run(function () {
				return static::get()->toArray();
			});

	}


	/**
	 * Add a testimonial - called from Ajax
	 *
	 * @param $title
	 * @param $text
	 * @param $name
	 * @param $location
	 *
	 * @return array
	 */
	public static function addTestimonial($title, $text, $name, $location): array
	{
		$testimonial = static::create([
			'post_title'   => $title,
			'post_content' => $text,
			'name'         => $name,
			'location'     => $location,
			'post_status'  => 'draft',
		]);

		return [
			'message' => 'Testimonial pending approval',
			'id'      => $testimonial->ID,
		];
	}


	/**
	 * @throws Exception
	 */
	public function checkValidity()
	{
		if (!$this->post_title) {
			throw ThemeException::withMessage('Please specify a title');
		}

		if (!array_key_exists($this->location, static::$locations)) {
			throw  ThemeException::withMessage('Invalid location ' . $this->location);
		}
	}
}
