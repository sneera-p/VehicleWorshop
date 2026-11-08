<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Http\Request\Request;
use Vwork\Web\Http\Response\Response;

/**
 * Handles Customer Dashboard flows.
 *
 * @phpstan-import-type StringMap from IController
 */
final readonly class CustomerDashboardController extends ControllerBase
{
    public function __construct(IRegistry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * Renders Customer Overview Dashboard (GET /customer/overview or GET /customer/dashboard)
     *
     * @param StringMap $attr
     */
    #[ControllerAction]
    public function overviewPage(Request $req, array $attr): Response
    {
        $vehicles = $this->getMockVehicles();
        $html = $this->view($req, 'customer/dashboard', [
            'title'           => 'Overview - AutoCare Customer Portal',
            'active_route'    => 'overview',
            'user'            => null,
            'stats'           => [
                'active_vehicles'  => count($vehicles),
                'vehicles_in_shop' => 1,
            ],
            'current_service' => [
                'status'             => 'In Progress',
                'vehicle_name'       => '2021 Land Cruiser Prado',
                'license_plate'      => 'WP CAB-7892',
                'completion_percent' => 65,
                'milestones'         => [
                    ['label' => 'Vehicle Received', 'status' => 'done'],
                    ['label' => 'Diagnostic Scan', 'status' => 'done'],
                    ['label' => 'Parts Replacement', 'status' => 'active'],
                    ['label' => 'Final QA & Wash', 'status' => 'pending'],
                ],
            ],
            'recent_activity' => [
                ['title' => 'Diagnostic report completed for WP CAB-7892', 'time_label' => '10:45 AM'],
                ['title' => 'Scheduled inspection booked for WP CBA-4321', 'time_label' => 'Yesterday'],
            ],
        ], 'layouts/customer');

        return Response::single()->html($html);
    }

    /**
     * Renders Customer Vehicles Page (GET /customer/vehicles)
     *
     * @param StringMap $attr
     */
    #[ControllerAction]
    public function vehiclesPage(Request $req, array $attr): Response
    {
        $vehicles = $this->getMockVehicles();
        $html = $this->view($req, 'customer/vehicles', [
            'title'        => 'My Vehicles - AutoCare Customer Portal',
            'active_route' => 'vehicles',
            'user'         => null,
            'vehicles'     => $vehicles,
        ], 'layouts/customer');

        return Response::single()->html($html);
    }

    /**
     * Renders Customer Appointments Page (GET /customer/appointments)
     *
     * @param StringMap $attr
     */
    #[ControllerAction]
    public function appointmentsPage(Request $req, array $attr): Response
    {
        $appointments = $this->getMockAppointments();
        $scheduledToday = 0;
        $upcomingCount = count(array_filter(
            $appointments,
            static fn (array $a): bool => in_array($a['status'], ['Upcoming', 'Confirmed'], true)
        ));
        $servicedThisYear = count(array_filter(
            $appointments,
            static fn (array $a): bool => $a['status'] === 'Completed'
        ));

        $html = $this->view($req, 'customer/appointments', [
            'title'        => 'Appointments - AutoCare Customer Portal',
            'active_route' => 'appointments',
            'user'         => null,
            'stats'        => [
                'scheduled_today'    => $scheduledToday,
                'upcoming_bookings'  => $upcomingCount,
                'serviced_this_year' => $servicedThisYear,
            ],
            'appointments' => $appointments,
        ], 'layouts/customer');

        return Response::single()->html($html);
    }

    /**
     * Renders customer repair history (GET /customer/history).
     *
     * @param StringMap $attr
     */
    #[ControllerAction]
    public function historyPage(Request $req, array $attr): Response
    {
        $repairs = $this->getMockRepairs();
        $html = $this->view($req, 'customer/history', [
            'title'        => 'Repair History - AutoCare Customer Portal',
            'active_route' => 'history',
            'user'         => null,
            'repairs'      => $repairs,
        ], 'layouts/customer');

        return Response::single()->html($html);
    }

    /**
     * @return list<array{
     *     id: int,
     *     date: string,
     *     time: string,
     *     vehicle_model: string,
     *     license_plate: string,
     *     service_type: string,
     *     service_color: string,
     *     status: 'Upcoming'|'Completed'|'Confirmed'|'Cancelled'
     * }>
     */
    private function getMockAppointments(): array
    {
        return [
            [
                'id'            => 1,
                'date'          => 'Oct 12, 2026',
                'time'          => '09:00 AM',
                'vehicle_model' => '2018 Toyota Axio',
                'license_plate' => 'WP CBA-4321',
                'service_type'  => 'Full Service',
                'service_color' => '#f97316',
                'status'        => 'Upcoming',
            ],
            [
                'id'            => 2,
                'date'          => 'Sep 01, 2026',
                'time'          => '10:30 AM',
                'vehicle_model' => '2015 Honda Fit',
                'license_plate' => 'NW WP-9876',
                'service_type'  => 'Brake Replacement',
                'service_color' => '#3b82f6',
                'status'        => 'Completed',
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     date: string,
     *     vehicle_model: string,
     *     license_plate: string,
     *     service: string,
     *     amount: int,
     *     mechanic: string,
     *     details: string
     * }>
     */
    private function getMockRepairs(): array
    {
        return [
            [
                'id'            => 1,
                'date'          => 'Sep 01, 2026',
                'vehicle_model' => '2015 Honda Fit',
                'license_plate' => 'NW WP-9876',
                'service'       => 'Brake Replacement',
                'amount'        => 38500,
                'mechanic'      => 'Dishan Karunaratne',
                'details'       => 'Front brake pads and discs replaced; road test completed.',
            ],
            [
                'id'            => 2,
                'date'          => 'Jun 14, 2026',
                'vehicle_model' => '2018 Toyota Axio',
                'license_plate' => 'WP CBA-4321',
                'service'       => 'Oil and Filter Service',
                'amount'        => 24800,
                'mechanic'      => 'Sahan Nimesh',
                'details'       => 'Engine oil and oil filter replaced; multi-point inspection completed.',
            ],
            [
                'id'            => 3,
                'date'          => 'Feb 22, 2026',
                'vehicle_model' => '2021 Land Cruiser Prado',
                'license_plate' => 'WP CAB-7892',
                'service'       => 'Transmission Service',
                'amount'        => 96400,
                'mechanic'      => 'Manjula Kulatunga',
                'details'       => 'Transmission fluid and differential oil serviced.',
            ],
            [
                'id'            => 4,
                'date'          => 'Nov 08, 2025',
                'vehicle_model' => '2018 Toyota Axio',
                'license_plate' => 'WP CBA-4321',
                'service'       => 'Engine Air Filter Replacement',
                'amount'        => 12800,
                'mechanic'      => 'Sahan Nimesh',
                'details'       => 'Engine air filter replaced and intake system checked.',
            ],
            [
                'id'            => 5,
                'date'          => 'Aug 19, 2025',
                'vehicle_model' => '2020 BMW 330e M-Sport',
                'license_plate' => 'WP CAD-1029',
                'service'       => 'Hybrid System Inspection',
                'amount'        => 54200,
                'mechanic'      => 'Dilan Perera',
                'details'       => 'Hybrid battery health and high-voltage system inspected.',
            ],
            [
                'id'            => 6,
                'date'          => 'May 03, 2025',
                'vehicle_model' => '2015 Honda Fit',
                'license_plate' => 'NW WP-9876',
                'service'       => 'Tire Replacement',
                'amount'        => 78600,
                'mechanic'      => 'Dishan Karunaratne',
                'details'       => 'Four tires replaced and wheel alignment checked.',
            ],
            [
                'id'            => 7,
                'date'          => 'Jan 17, 2025',
                'vehicle_model' => '2021 Land Cruiser Prado',
                'license_plate' => 'WP CAB-7892',
                'service'       => 'Full Service',
                'amount'        => 112500,
                'mechanic'      => 'Manjula Kulatunga',
                'details'       => 'Scheduled full service including fluids, filters, and safety inspection.',
            ],
            [
                'id'            => 8,
                'date'          => 'Oct 11, 2024',
                'vehicle_model' => '2018 Toyota Axio',
                'license_plate' => 'WP CBA-4321',
                'service'       => 'Battery Replacement',
                'amount'        => 46800,
                'mechanic'      => 'Sahan Nimesh',
                'details'       => '12V battery replaced and charging system tested.',
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     license_plate: string,
     *     model: string,
     *     trim_badge: string,
     *     image: string,
     *     color_name: string,
     *     color_hex: string,
     *     status: 'active_service'|'idle',
     *     bay_status_label?: string
     * }>
     */
    private function getMockVehicles(): array
    {
        return [
            [
                'id'            => 1,
                'license_plate' => 'WP CBA-4321',
                'model'         => '2018 Toyota Axio',
                'trim_badge'    => '1.5L Hybrid e-CVT',
                'image'         => 'https://images.unsplash.com/photo-1590362891988-3932824e438e?auto=format&fit=crop&w=800&q=80',
                'color_name'    => 'Pearl White',
                'color_hex'     => '#ffffff',
                'status'        => 'idle',
            ],
            [
                'id'               => 2,
                'license_plate'    => 'WP CAB-7892',
                'model'            => '2021 Land Cruiser Prado',
                'trim_badge'       => '2.8L D-4D 4WD',
                'image'            => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=800&q=80',
                'color_name'       => 'Attitude Black',
                'color_hex'        => '#0f172a',
                'status'           => 'active_service',
                'bay_status_label' => 'View Progress',
            ],
            [
                'id'            => 3,
                'license_plate' => 'WP CAD-1029',
                'model'         => '2020 BMW 330e M-Sport',
                'trim_badge'    => '2.0L PHEV 292hp',
                'image'         => 'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=800&q=80',
                'color_name'    => 'Mineral Grey',
                'color_hex'     => '#64748b',
                'status'        => 'idle',
            ],
        ];
    }
}
