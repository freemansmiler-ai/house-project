<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\Landlord;
use App\Models\Agent;
use App\Models\Property;
use App\Models\Amenity;
use App\Models\PropertyImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admins
        $admin = User::create([
            'name' => 'PropertyHub Admin',
            'email' => 'admin@propertyhub.com.gh',
            'password' => bcrypt('adminpassword'),
            'phone' => '+233241234567',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $admin->id,
            'first_name' => 'PropertyHub',
            'last_name' => 'Administrator',
            'bio' => 'Platform director for PropertyHub Ghana.',
            'city' => 'Accra',
            'region' => 'Greater Accra',
        ]);

        // 2. Create Landlords
        $landlordUser = User::create([
            'name' => 'Kofi Mensah',
            'email' => 'kofi@mensahproperties.com',
            'password' => bcrypt('password123'),
            'phone' => '+233201112222',
            'role' => 'landlord',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $landlordUser->id,
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'bio' => 'Independent residential property landlord in East Legon.',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'whatsapp_number' => '+233201112222'
        ]);

        Landlord::create([
            'user_id' => $landlordUser->id,
            'company_name' => 'Mensah Homes Ltd',
            'tax_id' => 'GHA-9922883-9',
            'is_verified' => true,
            'total_properties' => 1
        ]);

        // 3. Create Agents
        $agentUser = User::create([
            'name' => 'Ama Serwaa',
            'email' => 'ama@goldcoastagents.com',
            'password' => bcrypt('password123'),
            'phone' => '+233273334444',
            'role' => 'agent',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $agentUser->id,
            'first_name' => 'Ama',
            'last_name' => 'Serwaa',
            'bio' => 'Certified real estate consultant specializing in premium apartments.',
            'city' => 'Kumasi',
            'region' => 'Ashanti',
            'whatsapp_number' => '+233273334444'
        ]);

        Agent::create([
            'user_id' => $agentUser->id,
            'agency_name' => 'Gold Coast Realty',
            'license_number' => 'REA-GH-2026-88',
            'experience_years' => 5,
            'is_verified' => true,
            'rating' => 4.85
        ]);

        // 4. Create Amenities
        $amenities = [
            ['name' => 'Air Conditioning', 'icon' => 'ac'],
            ['name' => 'Standby Generator', 'icon' => 'generator'],
            ['name' => 'Water Reservoir (Polytank)', 'icon' => 'water'],
            ['name' => 'Walled and Gated', 'icon' => 'security'],
            ['name' => 'Swimming Pool', 'icon' => 'pool'],
            ['name' => 'Borehole System', 'icon' => 'borehole'],
            ['name' => 'High-Speed Wi-Fi', 'icon' => 'wifi'],
            ['name' => '24/7 Security Guard', 'icon' => 'guard']
        ];

        $seededAmenities = [];
        foreach ($amenities as $a) {
            $seededAmenities[] = Amenity::create($a);
        }

        // 5. Create Properties
        $property1 = Property::create([
            'user_id' => $landlordUser->id,
            'title' => 'Luxury 3-Bedroom Townhouse in East Legon',
            'slug' => 'luxury-3-bedroom-townhouse-in-east-legon',
            'description' => 'A beautiful contemporary townhouse situated in a gated community in the heart of East Legon, Accra. Boasting top-of-the-line fittings, a standby generator, polytank water storage, and close proximity to international schools and malls.',
            'price' => 50000.00,
            'currency' => 'GHS',
            'period' => 'month',
            'category' => 'residential',
            'type' => 'townhouse',
            'status' => 'active',
            'deal_type' => 'rent',
            'bedrooms' => 3,
            'bathrooms' => 3.5,
            'area' => 320,
            'location' => 'Adjiringanor Road, East Legon',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'is_featured' => true,
            'view_count' => 125,
            'published_at' => now()
        ]);

        // Attach some amenities
        $property1->amenities()->attach([
            $seededAmenities[0]->id, // AC
            $seededAmenities[1]->id, // Generator
            $seededAmenities[2]->id, // Polytank
            $seededAmenities[3]->id, // Gated
            $seededAmenities[7]->id  // Guard
        ]);

        $property2 = Property::create([
            'user_id' => $agentUser->id,
            'title' => 'Premium 2-Bedroom Apartment at Airport Residential Area',
            'slug' => 'premium-2-bedroom-apartment-at-airport-residential-area',
            'description' => 'Fully furnished high-end apartment offering panoramic views of Accra. Enjoy access to a rooftop pool, private gym, security guards, high speed internet, and modern air conditioning system.',
            'price' => 250000.00,
            'currency' => 'GHS',
            'period' => null,
            'category' => 'residential',
            'type' => 'apartment',
            'status' => 'active',
            'deal_type' => 'sale',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area' => 140,
            'location' => 'Liberation Road, Airport Residential Area',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'is_featured' => false,
            'view_count' => 450,
            'published_at' => now()
        ]);

        // Attach some amenities
        $property2->amenities()->attach([
            $seededAmenities[0]->id, // AC
            $seededAmenities[1]->id, // Generator
            $seededAmenities[4]->id, // Pool
            $seededAmenities[6]->id, // Wifi
            $seededAmenities[7]->id  // Guard
        ]);

        // 6. Create Property Images for Gallery
        PropertyImage::create([
            'property_id' => $property1->id,
            'image_path' => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=80',
            'is_thumbnail' => true,
            'sort_order' => 0
        ]);
        PropertyImage::create([
            'property_id' => $property1->id,
            'image_path' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80',
            'is_thumbnail' => false,
            'sort_order' => 1
        ]);
        PropertyImage::create([
            'property_id' => $property1->id,
            'image_path' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=800&q=80',
            'is_thumbnail' => false,
            'sort_order' => 2
        ]);
        PropertyImage::create([
            'property_id' => $property1->id,
            'image_path' => 'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=800&q=80',
            'is_thumbnail' => false,
            'sort_order' => 3
        ]);

        PropertyImage::create([
            'property_id' => $property2->id,
            'image_path' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1200&q=80',
            'is_thumbnail' => true,
            'sort_order' => 0
        ]);
        PropertyImage::create([
            'property_id' => $property2->id,
            'image_path' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80',
            'is_thumbnail' => false,
            'sort_order' => 1
        ]);
        PropertyImage::create([
            'property_id' => $property2->id,
            'image_path' => 'https://images.unsplash.com/photo-1502005229762-fc1b2b812ca5?auto=format&fit=crop&w=800&q=80',
            'is_thumbnail' => false,
            'sort_order' => 2
        ]);

        // 7. Seed FAQs
        $faqs = [
            [
                'question' => 'How do I list a new property on PropertyHub?',
                'answer' => 'To list a property, log in to your account, navigate to your landlord/agent dashboard, and click on "Add New Property". Fill in the property details, upload quality images, select amenities, and submit. If you are an unverified user, your listing will go through an admin approval process before becoming active.',
                'category' => 'listings'
            ],
            [
                'question' => 'What are the fees for listing properties?',
                'answer' => 'Basic listings are free for all registered users up to 3 active properties. For premium exposure, featured listings, or higher listing limits, you can subscribe to our Landlord/Agent premium tiers under the Subscriptions section of your dashboard.',
                'category' => 'billing'
            ],
            [
                'question' => 'How do I verify my account as an Agent?',
                'answer' => 'Go to your profile settings, click on the "Verification" tab, and upload a valid government-issued ID and your real estate agent license. Our admin team will review your submission within 24-48 hours. Once verified, a blue badge will appear on your profile.',
                'category' => 'account'
            ],
            [
                'question' => 'Can tenants apply or book property visits directly?',
                'answer' => 'Yes! Tenants can browse listings, click the "Book Tour" button, select their preferred date/time, and send the request. Landlords/Agents will receive notification and can approve, reschedule, or communicate directly via the built-in messaging system.',
                'category' => 'general'
            ],
            [
                'question' => 'What payment methods do you accept for subscriptions?',
                'answer' => 'We accept all major mobile money channels (MTN Mobile Money, Telecel Cash, AT Money) and credit/debit cards (Visa, MasterCard) through our secure payment gateway.',
                'category' => 'billing'
            ]
        ];

        foreach ($faqs as $faq) {
            \App\Models\Faq::create($faq);
        }

        // 8. Seed Support Tickets & Replies
        $ticket1 = \App\Models\SupportTicket::create([
            'user_id' => $landlordUser->id,
            'ticket_number' => 'TIC-LL882736',
            'subject' => 'Unable to upload high-resolution images',
            'description' => 'I am trying to list my East Legon townhouse but the upload keeps failing when I add 5K photos. Is there an image size limit on the upload tool?',
            'category' => 'listings',
            'priority' => 'medium',
            'status' => 'in_progress'
        ]);

        \App\Models\SupportTicketReply::create([
            'support_ticket_id' => $ticket1->id,
            'user_id' => $admin->id,
            'message' => 'Hello Kofi. Yes, there is currently an upload limit of 10MB per image to optimize page load speeds. Please try resizing your photos to under 10MB or converting them to JPEGs/PNGs. Let me know if that resolves your issue!'
        ]);

        $ticket2 = \App\Models\SupportTicket::create([
            'user_id' => $agentUser->id,
            'ticket_number' => 'TIC-AG119934',
            'subject' => 'Request to update my agency name',
            'description' => 'My license was updated and I would like to rename my registered agency name from Gold Coast Realty to Premium Gold Coast Real Estate. Please assist.',
            'category' => 'account',
            'priority' => 'low',
            'status' => 'open'
        ]);
    }
}
