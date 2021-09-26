<?php

namespace App\EventListener;

use ApiPlatform\Core\EventListener\EventPriorities;

use App\Repository\BookingRepository;
use App\Repository\UserRepository;

use App\Entity\Booking;
use App\Entity\User;

use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


final class DeleteBookingSubscriber implements EventSubscriberInterface
{
     /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    private $doctrine;
    private $logger;
    private $repository;
    private $userRepository;
    private $security;

    public function __construct(ManagerRegistry $doctrine, LoggerInterface $logger, BookingRepository $repository, UserRepository $userRepository, Security $security, TokenStorageInterface $tokenStorage)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['deleteBooking', EventPriorities::PRE_DESERIALIZE],
        ];
    }

    public function deleteBooking(RequestEvent $event)
    {

        $request = $event->getRequest();


        if ('api_bookings_delete_item' !== $request->attributes->get('_route')) {
            return;
        }

      // check if the user is an admin BDE or can edit events for this event
      if (!($this->security->isGranted('ROLE_R0_A1') || $this->security->isGranted('ROLE_R3_A' . $event->getAssociation()->getId())))  throw new AccessDeniedException();;

         // if not, the user is not valid
         $responseData = array(
            "message" => "Invalid user"
        );
        $response = new JsonResponse($responseData, Response::HTTP_UNAUTHORIZED);
        $event->setResponse($response);

        $path = $request->getPathInfo();
        $this->logger->info($path);
        $path = explode("/", $path);
        $bookingId = end($path);
        $this->logger->info($bookingId);

        $oldBooking = $this->repository->find($bookingId);

        // prevent the user from deleting the reservation if it has been validated by an association member
        // only allow BDE Admin and association member with event editing rights
        $token = $this->tokenStorage->getToken();
        if(!$token) throw new AccessDeniedException();

        $owner = $token->getUser();
        
        $isAdminOnEvent = $this->security->isGranted('ROLE_R0_A1') || $this->security->isGranted('ROLE_R3_A' . $oldBooking->getEvent()->getAssociation()->getId());
        if( (!$oldBooking->getValidated() && $owner == $oldBooking->getUser() || $isAdminOnEvent) == false ){
              throw new AccessDeniedException();
        }

        $operationId = $oldBooking->getCercleOperationId();

        if (!is_null($operationId)) {
            $client = new Client([
                'base_uri' => $_ENV['CERCLE_API_URL'],
            ]);

            $headers = [
                'LOGIN' => $_ENV['CERCLE_API_LOGIN'],
                'PWD' => $_ENV['CERCLE_API_PWD'],
            ];
            $body = json_encode(["id"=>$operationId]);

            $client->delete('delete_transaction.php', ['headers' => $headers, 'body' => $body]);
        }
        return;
    }
}