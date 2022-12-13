<?php

namespace ShoppingFeed\Command;

use ShoppingFeed\Model\ShoppingfeedFeedQuery;
use ShoppingFeed\Service\FeedService;
use ShoppingFeed\Service\OrderService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;

class SynchronizeOrderCommand extends ContainerAwareCommand
{
    protected OrderService $orderService;

    /**
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        parent::__construct();
        $this->orderService = $orderService;
    }

    protected function configure()
    {
        $this
            ->setName("shopping_feed:sync:order")
            ->setDescription("Synchronize order from Shopping Feed");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initRequest();

        $feeds = ShoppingfeedFeedQuery::create()->find();

        foreach ($feeds as $feed) {
            $this->orderService->importOrders($feed);
        }

        return 1;
    }
}