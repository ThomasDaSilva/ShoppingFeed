<?php

namespace ShoppingFeed\Form;

use ShoppingFeed\ShoppingFeed;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\ModuleQuery;
use Symfony\Component\Validator\Constraints;


class MappingDeliveryForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "code",
                TextType::class,
                [
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label"=> Translator::getInstance()->trans("Code", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                ]
            )
            ->add(
                "module_id",
                ChoiceType::class,
                [
                    "constraints" => [
                        new Constraints\NotBlank(),
                    ],
                    "label"=> Translator::getInstance()->trans("Delivery Module", [], ShoppingFeed::DOMAIN_NAME),
                    "required" => true,
                    "choices" => $this->getDeliveryModules()
                ]
            )
        ;
    }

    public function getDeliveryModules()
    {
        $deliveryModules = ModuleQuery::create()
            ->filterByType(2)
            ->filterByCategory("delivery")
            ->filterByActivate(1)
            ->find();

        $results = [];

        foreach ($deliveryModules as $deliveryModule) {
            $results[$deliveryModule->getTitle()] = $deliveryModule->getId();
        }

        return $results;
    }

    public static function getName()
    {
        return "shoppingfeed_mapping_delivery_form";
    }
}
