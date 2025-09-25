<?php

namespace SocExtAffairs\ReservationDataAnalysis\Controller;

use Twig\Environment;
use SocExtAffairs\ReservationDataAnalysis\Entity\Reservation;

class ReservationController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function index(): string
    {
        $reservations = $this->getAllReservations();
        
        return $this->twig->render('reservations/index.html.twig', [
            'reservations' => $reservations
        ]);
    }

    public function create(): string
    {
        return $this->twig->render('reservations/create.html.twig');
    }

    public function store(): void
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'create_reservation')) {
            wp_die('Unauthorized');
        }

        $reservation = new Reservation(
            sanitize_text_field($_POST['groupName']),
            sanitize_text_field($_POST['dateOfEvent']),
            sanitize_text_field($_POST['locationName']),
            intval($_POST['duration'])
        );

        $this->saveReservation($reservation);
        wp_redirect(admin_url('admin.php?page=reservations&message=created'));
        exit;
    }

    private function getAllReservations(): array
    {
        $posts = get_posts([
            'post_type' => 'reservation_data',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $reservations = [];
        foreach ($posts as $post) {
            $reservation = new Reservation(
                get_post_meta($post->ID, 'groupName', true),
                get_post_meta($post->ID, 'dateOfEvent', true),
                get_post_meta($post->ID, 'locationName', true),
                get_post_meta($post->ID, 'duration', true)
            );
            $reservation->setId($post->ID);
            $reservations[] = $reservation;
        }

        return $reservations;
    }

    private function saveReservation(Reservation $reservation): void
    {
        wp_insert_post([
            'post_type' => 'reservation_data',
            'post_title' => $reservation->getGroupName(),
            'post_status' => 'publish',
            'meta_input' => [
                'groupName' => $reservation->getGroupName(),
                'dateOfEvent' => $reservation->getDateOfEvent(),
                'locationName' => $reservation->getLocationName(),
                'duration' => $reservation->getDuration(),
                'hash' => $reservation->getHash()
            ]
        ]);
    }
}