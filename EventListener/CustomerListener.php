<?php
namespace Webburza\Sylius\WishlistBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\User\Model\CustomerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Translator;
use Webburza\Sylius\WishlistBundle\Model\WishlistInterface;
use Webburza\Sylius\WishlistBundle\Model\WishlistRepositoryInterface;

class CustomerListener
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * WishlistListener constructor.
     *
     * @param DataCollectorTranslator $translator
     * @param ContainerInterface $container
     */
    public function __construct(DataCollectorTranslator $translator, ContainerInterface $container)
    {
        $this->translator = $translator;
        $this->container = $container;

        $this->eventDispatcher = $container->get('event_dispatcher');
    }

    /**
     * Hook into the customer create event,
     * and create a default wishlist for the customer.
     *
     * @param ResourceControllerEvent $event
     */
    public function postCreate(ResourceControllerEvent $event)
    {
        /** @var CustomerInterface $customer */
        $customer = $event->getSubject();

        /** @var WishlistInterface $wishlist */
        $wishlist = $this->container->get('webburza.factory.wishlist')->createNew();
        $wishlist->setTitle($this->translator->trans('webburza.sylius.wishlist.default_title'));
        $wishlist->setCustomer($customer);

        $wishlist->setPublic(
            $this->container->getParameter('webburza.sylius.wishlist_bundle.default_public')
        );

        // Dispatch pre-create event
        $this->eventDispatcher->dispatch('webburza.wishlist.pre_create', new ResourceControllerEvent($wishlist));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('webburza.manager.wishlist');
        $entityManager->persist($wishlist);
        $entityManager->flush();

        // Dispatch post-create event
        $this->eventDispatcher->dispatch('webburza.wishlist.post_create', new ResourceControllerEvent($wishlist));
    }

    /**
     * Hook into the customer post-delete event,
     * and delete all wishlists for the customer.
     *
     * @param ResourceControllerEvent $event
     */
    public function postDelete(ResourceControllerEvent $event)
    {
        /** @var CustomerInterface $customer */
        $customer = $event->getSubject();

        /** @var WishlistRepositoryInterface $wishlistRepository */
        $wishlistRepository = $this->container->get('webburza.repository.wishlist');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('webburza.manager.wishlist');

        // Get all the wishlists for the customer
        $wishlists = $wishlistRepository->getWishlistsForCustomer($customer);

        // Remove each wishlist
        foreach ($wishlists as $wishlist) {
            $entityManager->remove($wishlist);
        }

        // Flush changes
        if (!empty($wishlists)) {
            $entityManager->flush();
        }
    }
}