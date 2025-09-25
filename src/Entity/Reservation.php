<?php

namespace SocExtAffairs\ReservationDataAnalysis\Entity;

class Reservation
{
    private ?int $id = null;
    private string $groupName;
    private string $dateOfEvent;
    private string $locationName;
    private int $duration;
    private string $hash;

    public function __construct(string $groupName, string $dateOfEvent, string $locationName, int $duration)
    {
        $this->groupName = $groupName;
        $this->dateOfEvent = $dateOfEvent;
        $this->locationName = $locationName;
        $this->duration = $duration;
        $this->generateHash();
    }

    private function generateHash(): void
    {
        $this->hash = hash('sha256', $this->groupName . $this->dateOfEvent . $this->locationName . $this->duration);
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getGroupName(): string { return $this->groupName; }
    public function getDateOfEvent(): string { return $this->dateOfEvent; }
    public function getLocationName(): string { return $this->locationName; }
    public function getDuration(): int { return $this->duration; }
    public function getHash(): string { return $this->hash; }

    // Setters
    public function setId(int $id): void { $this->id = $id; }
}