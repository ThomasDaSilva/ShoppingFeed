<?php

namespace ShoppingFeed\Service;

use Propel\Runtime\Propel;
use ShoppingFeed\Exception\ShoppingfeedException;
use ShoppingFeed\Model\ShoppingfeedFeed;
use ShoppingFeed\Model\ShoppingfeedMappingDeliveryQuery;
use ShoppingFeed\Model\ShoppingFeedOrderData;
use ShoppingFeed\Model\ShoppingfeedOrderDataQuery;
use ShoppingFeed\Sdk\Api\Order\OrderOperation;
use ShoppingFeed\ShoppingFeed;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18n;
use Thelia\Tools\I18n;

class OrderService
{
    protected $apiService;
    protected $logger;
    protected $mappingDeliveryService;

    public function __construct(ApiService $apiService, LogService $logger, MappingDeliveryService $mappingDeliveryService)
    {
        $this->apiService = $apiService;
        $this->logger = $logger;
        $this->mappingDeliveryService = $mappingDeliveryService;
    }

    public function importOrders(ShoppingfeedFeed $feed)
    {
        $orderApi = $this->apiService->getFeedStore($feed)->getOrderApi();
        $orders = $orderApi->getAll(['filters' => ['acknowledgment' => 'unacknowledged']]);
        $customer = ShoppingFeed::getSoppingFeedCustomer();
        $orderOperation = new OrderOperation();

        $paidStatus = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);
        $nbImportedOrder = 0;

        foreach ($orders as $order) {
            try {
                $con = Propel::getConnection(
                    OrderTableMap::DATABASE_NAME
                );

                $con->beginTransaction();

                $shoppingFeedOrderData = ShoppingfeedOrderDataQuery::create()->filterByExternalReference($order->getReference())->findOne();
                if (null !== $shoppingFeedOrderData && !$shoppingFeedOrderData->getOrder()->getOrderStatus()->isCancelled()) {
                    throw new ShoppingfeedException(
                        $feed,
                        Translator::getInstance()->trans(
                            "This order has already been imported.",
                            [],
                            ShoppingFeed::DOMAIN_NAME
                        ),
                        Translator::getInstance()->trans(
                            "To import this command, cancel previous order (see link in extra)",
                            [],
                            ShoppingFeed::DOMAIN_NAME
                        ),
                        LogService::LEVEL_WARNING,
                        $shoppingFeedOrderData->getId(),
                        'Order',
                        $order->getReference()
                    );
                }

                $billingAddress = $order->getBillingAddress();
                $theliaInvoiceAddress = $this->createAddressFromData($billingAddress);
                $theliaInvoiceAddress->save($con);

                $shippingAddress = $order->getShippingAddress();
                $theliaDeliveryAddress = $this->createAddressFromData($shippingAddress);
                $theliaDeliveryAddress->save($con);

                $currency =  CurrencyQuery::create()
                    ->filterByCode($order->getPaymentInformation()['currency'])
                    ->findOne();

                $deliveryModuleId = $this->mappingDeliveryService->getDeliveryModuleIdFromCode($order->getShipment()["carrier"]);
                if ($deliveryModuleId === 0) {
                    throw new ShoppingfeedException(
                        $feed,
                        Translator::getInstance()->trans(
                            "This delivery code mapping does not exists.",
                            [],
                            ShoppingFeed::DOMAIN_NAME
                        ),
                        Translator::getInstance()->trans(
                            "To create this mapping, go to Mapping Delivery tab (see link in extra)",
                            [],
                            ShoppingFeed::DOMAIN_NAME
                        ),
                        LogService::LEVEL_ERROR,
                        null,
                        'Mapping',
                        $order->getShipment()["carrier"]
                    );
                }

                $theliaOrder = (new Order())
                    ->setCustomer($customer)
                    ->setInvoiceOrderAddressId($theliaInvoiceAddress->getId())
                    ->setDeliveryOrderAddressId($theliaDeliveryAddress->getId())
                    ->setCurrencyId($currency->getId())
                    ->setPostage($order->getPaymentInformation()['shippingAmount'])
                    ->setPaymentModuleId(ShoppingFeed::getModuleId())
                    ->setDeliveryModuleId($deliveryModuleId)
                    ->setStatusId($paidStatus->getId())
                    ->setLangId($feed->getLangId());

                $theliaOrder->save($con);

                $shoppingFeedOrderData = (new ShoppingfeedOrderData())
                    ->setExternalReference($order->getReference())
                    ->setFeedId($feed->getId())
                    ->setId($theliaOrder->getId())
                    ->setChannel($order->getChannel()->getName())
                    ->setEmail($billingAddress['email']);

                $shoppingFeedOrderData->save($con);

                foreach ($order->getItems() as $item) {
                    $productSaleElements = ProductSaleElementsQuery::create()
                        ->filterByRef($item->getReference())
                        ->_or()
                        ->filterByEanCode($item->getReference())
                        ->_or()
                        ->useProductQuery()
                        ->filterByRef($item->getReference())
                        ->endUse()
                        ->findOne();

                    $product = $productSaleElements->getProduct();
                    $product->setLocale($feed->getLang()->getLocale());

                    $orderProduct = (new OrderProduct())
                        ->setOrderId($theliaOrder->getId())
                        // Data from thelia product
                        ->setTitle($product->getTitle())
                        ->setChapo($product->getChapo())
                        ->setDescription($product->getDescription())
                        ->setPostscriptum($product->getPostscriptum())
                        // Data from shopping feed
                        ->setQuantity($item->getQuantity())
                        ->setProductRef($item->getReference())
                        ->setProductSaleElementsRef($item->getReference())
                        ->setEanCode($item->getReference())
                        ->setPrice($item->getUnitPrice());

                    $orderProduct->save($con);

                    $orderProductTax = (new OrderProductTax())
                        ->setOrderProductId($orderProduct->getId())
                        ->setTitle("")
                        ->setAmount($item->getTaxAmount());

                    $orderProductTax->save($con);

                    /* fulfill order_attribute_combination and decrease stock */
                    foreach ($productSaleElements->getAttributeCombinations() as $attributeCombination) {
                        /** @var \Thelia\Model\Attribute $attribute */
                        $attribute = I18n::forceI18nRetrieving($feed->getLang()->getLocale(), 'Attribute', $attributeCombination->getAttributeId());

                        /** @var \Thelia\Model\AttributeAv $attributeAv */
                        $attributeAv = I18n::forceI18nRetrieving($feed->getLang()->getLocale(), 'AttributeAv', $attributeCombination->getAttributeAvId());

                        $orderAttributeCombination = new OrderProductAttributeCombination();
                        $orderAttributeCombination
                            ->setOrderProductId($orderProduct->getId())
                            ->setAttributeTitle($attribute->getTitle())
                            ->setAttributeChapo($attribute->getChapo())
                            ->setAttributeDescription($attribute->getDescription())
                            ->setAttributePostscriptum($attribute->getPostscriptum())
                            ->setAttributeAvTitle($attributeAv->getTitle())
                            ->setAttributeAvChapo($attributeAv->getChapo())
                            ->setAttributeAvDescription($attributeAv->getDescription())
                            ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                            ->save($con);
                    }
                }
                $con->commit();
                $orderOperation->acknowledge($order->getReference(), $order->getChannel()->getName(), $theliaOrder->getRef());
                $nbImportedOrder++;
            } catch (ShoppingfeedException $shoppingfeedException) {
                $con->rollBack();
                $this->logger->logShoppingfeedException($shoppingfeedException);
            } catch (\Exception $exception) {
                $con->rollBack();
                $this->logger->log(
                    $feed,
                    $exception->getMessage(),
                    LogService::LEVEL_ERROR,
                    null,
                    'Order',
                    $order->getReference()
                );
            }
        }
        $orderApi->execute($orderOperation);
        if ($nbImportedOrder > 0) {
            $this->logger->log(
                $feed,
                $nbImportedOrder.' order(s) have been imported successfully.',
                LogService::LEVEL_SUCCESS
            );
        }
    }

    protected function createAddressFromData($data)
    {
        return (new OrderAddress())
            ->setFirstname($data['firstName'])
            ->setLastname($data['lastName'])
            ->setCompany($data['company'])
            ->setAddress1($data['street'])
            ->setAddress2($data['street2'])
            ->setAddress3($data['other'])
            ->setZipcode($data['postalCode'])
            ->setCountry($this->getCountryByIsoAlpha2($data['country']))
            ->setCity($data['city'])
            ->setPhone($data['phone'])
            ->setCellphone($data['mobilePhone']);
    }

    public function getCountryByIsoAlpha2($isoAlpha2)
    {
        $country = CountryQuery::create()->filterByIsoalpha2($isoAlpha2)->findOne();
        if (null === $country) {
            $country =  CountryQuery::create()->filterByIsoalpha2('FR')->findOne();
        }
        return $country;
    }

    public function _importOrders(ShoppingfeedFeed $feed, $url = "/v1/store/{storeId}/order?acknowledgement=unacknowledged&limit=2")
    {
        $orderListResponse = $this->apiService->request($feed, $url);
        $shoppingFeedCustomer = ShoppingFeed::getSoppingFeedCustomer();

        foreach ($orderListResponse->_embedded->order as $order) {
            $theliaOrder = (new Order());
        }

        if (property_exists($orderListResponse->_links, 'next')) {
            $this->importOrders($feed, $orderListResponse->_links->next->href);
        }
    }
}