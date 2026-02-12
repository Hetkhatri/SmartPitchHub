# Smart Pitch Hub - Admin Panel

## Overview

The admin panel provides comprehensive management capabilities for the Smart Pitch Hub platform. It allows administrators to manage users, pitches, and monitor platform activity.

## Features

### User Management

- View all registered users (investors, entrepreneurs, admins)
- Activate/deactivate user accounts
- Delete users (soft delete)
- View user activity statistics

### Pitch Management

- View all startup pitches
- Approve/reject pitches
- Edit pitch details
- Monitor funding progress
- View pitch statistics

### Dashboard

- System overview with key metrics
- Recent user activity
- Recent pitch submissions
- Quick action buttons

## Installation & Setup

1. **Database Setup**:

   - Run the setup script to create necessary tables:

   ```bash
   # Access via browser:
   http://your-domain.com/SmartPitchHub-1/admin/setup_database.php
   ```

2. **Default Admin Credentials**:

   - Email: admin@smartpitchhub.com
   - Password: admin123

3. **Access Admin Panel**:
   - Navigate to: `http://your-domain.com/SmartPitchHub-1/admin/login.php`

## File Structure

```
admin/
├── index.php              # Main admin dashboard
├── login.php              # Admin login page
├── dashboard.php          # Detailed dashboard with statistics
├── users.php             # User management
├── pitches.php           # Pitch management
├── logout.php            # Logout functionality
├── setup_database.php    # Database setup script
├── README.md             # This file
└── includes/
    ├── header.php        # Admin header template
    └── footer.php        # Admin footer template
```

## Database Tables

The admin panel uses the following tables:

1. **users** - Stores all user accounts
2. **pitches** - Stores startup pitches
3. **investments** - Stores investment records

## Security Features

- Session-based authentication
- Role-based access control
- Input validation and sanitization
- SQL injection prevention
- CSRF protection (recommended to add)

## Customization

### Adding New Features

1. Create new PHP files in the admin directory
2. Update the navigation in `includes/header.php`
3. Ensure proper session validation in each file

### Styling

- Uses the main `css/style.css` from the parent directory
- Additional admin-specific styles can be added in individual files

## Troubleshooting

### Common Issues

1. **Database Connection Error**: Check `../db.php` configuration
2. **Session Issues**: Ensure `session_start()` is called at the beginning of each file
3. **Permission Denied**: Verify file permissions and database user privileges

### Support

For technical support, check the main project documentation or contact the development team.

## Version History

- v1.0.0 - Initial release with basic admin functionality
- Includes user management, pitch management, and dashboard features

## Future Enhancements

- Advanced reporting and analytics
- Email notifications
- Bulk operations
- Export functionality
- Audit logging
