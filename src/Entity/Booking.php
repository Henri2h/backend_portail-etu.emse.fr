<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Datetime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Psr\Log\LoggerInterface;


use Symfony\Component\Security\Core\Security;


use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Decoder\JWTDecoderInterface;

//*              "access_control"="(is_granted('ROLE_USER') and object.getUser() == user) or is_granted('ROLE_R0_A1')",

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get"={"normalization_context"={"groups"={"get_bookings"}}},
 *         "post"={
 *              "normalization_context"={"groups"={"get_event_bookings"}},
 *              "denormalization_context"={"groups"={"post_booking"}},
 *              "access_control"="object.getEvent().getOpen() or (object.getUser() == user and object.getUser().getContributeBDE())"
 *          }
 *     },
 *     itemOperations={
 *          "get"={"normalization_context"={"groups"={"get_booking"}}},
 *          "delete"={"access_control"="(is_granted('ROLE_USER') and object.getUser() == user) or is_granted('ROLE_R0_A1') or ('ROLE_R3_A'~object.getEvent().getAssociation().getId() in roles)"},
 *          "put_validated_by"={
 *              "method"="PUT",
 *              "path"="/bookings/{id}/validate",
 *              "normalization_context"={"groups"={"set_validated_by"},
 *              "access_control"="is_granted('ROLE_R0_A1') or ('ROLE_R3_A'~object.getEvent().getAssociation().getId() in roles)"}
 *        
 *          },
 *          "put"={
 *              "normalization_context"={"groups"={"get_booking"}},
 *              "denormalization_context"={"groups"={"put_booking"}},
 *              "access_control"="(is_granted('ROLE_USER') and object.getUser() == user) or is_granted('ROLE_R0_A1') or ('ROLE_R3_A'~object.getEvent().getAssociation().getId() in roles)"
 *          },
 *     }
 * )
 * @ORM\Table(
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="booking_unique",
 *            columns={"user_id", "event_id"})
 *    }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\BookingRepository")
 */
class Booking
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"get_booking", "get_bookings", "user_info", "get_user", "put_booking", "get_event_bookings", "event_get"})
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"get_booking", "get_bookings", "post_booking", "put_booking", "user_info", "get_user", "get_event_bookings"})
     */
    private $paid = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\PaymentMeans", inversedBy="bookings")
     * @Groups({"get_booking", "post_booking", "put_booking", "get_event_bookings"})
     */
    private $paymentMeans;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="bookings")
     * @Groups({"get_booking", "get_bookings", "post_booking", "get_event_bookings", "event_get"})
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Event", inversedBy="bookings")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"get_booking", "get_bookings", "post_booking", "user_info", "get_user"})
     */
    private $event;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"post_booking", "get_booking", "get_bookings", "put_booking", "get_event_bookings", "event_get"})
     */
    private $userName;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Operation", mappedBy="booking", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Groups({"get_booking", "post_booking", "put_booking"})
     */
    private $operation;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FormOutput", mappedBy="booking", orphanRemoval=true, cascade={"persist", "remove"})
     * @Groups({"get_booking", "post_booking", "put_booking", "get_event_bookings"})
     */
    private $formOutputs;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     *  @Groups({"get_booking", "get_event_bookings"})
     */
    private $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"get_booking", "post_booking", "put_booking", "get_event_bookings"})
     */
    private $checked = false;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @Groups({"get_booking", "get_event_bookings", "set_validated_by"})
     */
    private $validated = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="bookings")
     * @Groups({"get_booking", "get_event_bookings", "set_validated_by"})
     */
    private $validatedBy;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cercleOperationId;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"post_booking", "put_booking", "get_booking"})
     */
    private $cercleOperationAmount;

    /**
     * @ORM\Column(name="date", type="datetime")
     *  @Groups({"get_booking", "post_booking", "get_event_bookings", "user_info"})
     */
    private $date;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->formOutputs = new ArrayCollection();
        $this->date = new DateTime('1900-01-01T0:0:0Z');
    }

    /**
     * @ORM\PreUpdate
     */
    public function updateDate()
    {
        $this->setUpdatedAt(new Datetime());
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $updatedAt
     */
    public function setUpdatedAt($updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaid(): ?bool
    {
        return $this->paid;
    }

    public function setPaid(?bool $paid): self
    {
        $this->paid = $paid;

        return $this;
    }

    public function getPaymentMeans(): ?PaymentMeans
    {
        return $this->paymentMeans;
    }

    public function setPaymentMeans(?PaymentMeans $payment_means): self
    {
        $this->paymentMeans = $payment_means;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(?bool $validated): self
    {
        $this->validated = $validated;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $user): self
    {
        $this->validatedBy = $user;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $user_name): self
    {
        $this->userName = $user_name;

        return $this;
    }

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function setOperation(?Operation $operation): self
    {
        $this->operation = $operation;

        // set (or unset) the owning side of the relation if necessary
        $newBooking = $operation === null ? null : $this;
        if (!is_null($operation) and $newBooking !== $operation->getBooking()) {
            $operation->setBooking($newBooking);
        }

        return $this;
    }

    /**
     * @return Collection|FormOutput[]
     */
    public function getFormOutputs(): Collection
    {
        return $this->formOutputs;
    }

    public function addFormOutput(FormOutput $formOutput): self
    {
        if (!$this->formOutputs->contains($formOutput)) {
            $this->formOutputs[] = $formOutput;
            $formOutput->setBooking($this);
        }

        return $this;
    }

    public function removeFormOutput(FormOutput $formOutput): self
    {
        if ($this->formOutputs->contains($formOutput)) {
            $this->formOutputs->removeElement($formOutput);
            // set the owning side to null (unless already changed)
            if ($formOutput->getBooking() === $this) {
                $formOutput->setBooking(null);
            }
        }

        return $this;
    }

    public function getChecked(): ?bool
    {
        return $this->checked;
    }

    public function setChecked(?bool $checked): self
    {
        $this->checked = $checked;

        return $this;
    }

    public function getCercleOperationId(): ?int
    {
        return $this->cercleOperationId;
    }

    public function setCercleOperationId(?int $cercleOperationId): self
    {
        $this->cercleOperationId = $cercleOperationId;

        return $this;
    }

    public function getCercleOperationAmount(): ?float
    {
        return $this->cercleOperationAmount;
    }

    public function setCercleOperationAmount(?float $cercleOperationAmount): self
    {
        $this->cercleOperationAmount = $cercleOperationAmount;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
}
