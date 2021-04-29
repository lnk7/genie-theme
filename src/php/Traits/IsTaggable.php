<?php

namespace Theme\Traits;

use ReflectionClass;

trait IsTaggable
{


    /**
     * get an array of available tags for this post Type
     *
     * @return array
     */
    public static function get_available_tags()
    {

        $tags = get_terms(static::get_taxonomy(), [
            'hide_empty' => false,
        ]);
        $availableTags = [];

        foreach ($tags as $tag) {
            $availableTags[] = $tag->name;
        }

        return $availableTags;
    }



    /**
     * Get all the tags used for this type.
     *
     * @return array
     */
    public function get_tags()
    {
        $terms = wp_get_post_terms($this->get_id(), static::get_taxonomy());
        $return = [];
        foreach ($terms as $term) {
            $return[] = $term->name;
        }

        return $return;
    }



    /**
     * Set tags for this order
     *
     * @param array $tags
     * @param bool $addTerm
     * @return $this
     */
    public function set_tags(array $tags, $addTerm = false)
    {


        $termIDs = [];
        foreach ($tags as $tag) {
            if (is_int($tag)) {
                $termIDs[] = (int)$tag;
                continue;
            }

            $term = get_term_by('name', $tag, static::get_taxonomy());
            if ($term) {
                $termIDs[] = $term->term_id;
                continue;
            }

            if ($addTerm) {
                $term = wp_insert_term($tag, static::get_taxonomy());
                $termIDs[] = $term['term_id'];
            }
        }

        wp_set_post_terms($this->get_id(), $termIDs, static::get_taxonomy());

        return $this;

    }



    public static function get_taxonomy()
    {

        $reflectionClass = new ReflectionClass(static::class);

        return sanitize_title($reflectionClass->getShortName()) . '_tag';

    }


}
