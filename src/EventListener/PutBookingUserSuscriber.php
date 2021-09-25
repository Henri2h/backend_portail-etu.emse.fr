<?php

namespace App\EventListener;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Symfony\Component\HttpFoundation\Response;

final class PutBookingUserSuscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;


    private $logger;

    public function __construct(TokenStorageInterface $tokenStorage, ManagerRegistry $doctrine, LoggerInterface $logger, BookingRepository $repository, UserRepository $userRepository)
    {
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['setUser', EventPriorities::PRE_WRITE],
        ];
    }

    public function setUser(ViewEvent $event)
    {
        $entity = $event->getControllerResult();
        $request = $event->getRequest();

        $set_user = true;
        $put = false;
        if ('api_bookings_post_collection' === $request->attributes->get('_route')) {
            $set_user = true;
        } else if ('api_bookings_put_validated_by_item' === $request->attributes->get('_route')) {
            $set_user = false;
        } else return;

        $this->logger->debug("app set_user : " . $set_user);

        // maybe these extra null checks are not even needed
        $token = $this->tokenStorage->getToken();

        if ($token) {
            $owner = $token->getUser();
            if ($owner instanceof User) {
                if ($set_user) {
                    $entity->setUser($owner);
                    
                } else {
                    $entity->setValidatedBy($owner);
                }
                return;
            }
        }

        $responseData = array(
            "message" => "Invalid user"
        );
        $response = new JsonResponse($responseData, Response::HTTP_UNAUTHORIZED);
        $event->setResponse($response);
    }
}
