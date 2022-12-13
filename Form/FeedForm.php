<?php

namespace ShoppingFeed\Form;

use ShoppingFeed\ShoppingFeed;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;


class FeedForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "country_id",
                TextType::class,
                [
                    "label"=> Translator::getInstance()->trans("Country", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                ]
            )
            ->add(
                "lang_id",
                TextType::class,
                [
                    "label"=> Translator::getInstance()->trans("Language", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                ]
            )
            ->add(
                "store_id",
                TextType::class,
                [
                    "label"=> Translator::getInstance()->trans("Store identifier", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                ]
            )
            ->add(
                "api_token",
                TextType::class,
                [
                    "label"=> Translator::getInstance()->trans("Api token", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                ]
            )
        ;
    }

    public static function getName()
    {
        return "shoppingfeed_feed_form";
    }
}
