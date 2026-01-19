# University Exam Timetable Management Platform

A complete web-based exam scheduling platform built with HTML, CSS, JavaScript, and PHP (no frameworks). This system manages exam schedules across multiple departments, formations, and roles while enforcing complex constraints and detecting scheduling conflicts.

## 🎯 Features

### ✅ Complete Implementation
- **5 User Roles**: Admin Examens, Vice-Doyen/Doyen, Chef de Département, Professeur, Étudiant
- **Role-Based Access Control**: Each user sees only their relevant dashboards and data
- **Secure Authentication**: Password hashing with PHP sessions
- **Real-time Scheduling**: Automatic exam timetable generation with constraint checking
- **Conflict Detection**: Identifies and reports scheduling conflicts with severity levels
- **Responsive UI**: Mobile-friendly Bootstrap-based design

### 👥 Actor Responsibilities

#### 1. **Admin Examens** (Service de Planification)
- Create/manage departments, formations, modules, professors, students, and exam rooms
- Generate automatic exam timetables
- Detect conflicts (student overlaps, professor overload, room capacity)
- Manage user accounts
- View global statistics

#### 2. **Vice-Doyen / Doyen**
- View global statistics and dashboards
- Monitor room occupancy rates
- Track professor workload
- Review conflicts across all departments
- View all scheduled exams

#### 3. **Chef de Département** (Department Head)
- View schedules for their department only
- Validate exams by department
- See statistics per formation
- Detect conflicts within their department
- Monitor department-specific resources

#### 4. **Professeur** (Professor)
- View personal exam schedule
- See assigned surveillances (invigilations)
- Track number of exams per day
- View teaching modules
- Monitor workload

#### 5. **Étudiant** (Student)
- View personal exam timetable
- Filter exams by formation and date
- See exam room and time details
- View enrolled modules
- Monitor exam schedule for conflicts

## 📋 Database Schema

### Core Tables
- **users**: Authentication and user accounts
- **departements**: Academic departments
- **formations**: Study programs/majors
- **modules**: Courses offered
- **etudiants**: Student records with enrollment data
- **professeurs**: Professor records with department assignment
- **salles**: Exam rooms/amphitheaters
- **inscriptions**: Student-module enrollment relationships
- **module_professeurs**: Professor-module teaching assignments
- **examens**: Scheduled exams
- **surveillances**: Exam supervision assignments
- **conflicts**: Detected scheduling conflicts

## ⚠️ Constraints Enforced

1. **Student Constraint**: Maximum 1 exam per day
2. **Professor Constraint**: Maximum 3 exams per day
3. **Room Constraint**: Room capacity cannot be exceeded
4. **Surveillance**: Fair distribution among professors by department
5. **Scheduling**: 4 time slots per day (08:00-10:00, 10:30-12:30, 14:00-16:00, 16:30-18:30)

## 🚀 Installation & Setup

### Prerequisites
- PHP 7.4+ with mysqli extension
- MySQL Server 5.7+
- Web Server (Apache/Nginx)
- Modern web browser

### Step 1: Extract Files
```bash
# Navigate to your web root (htdocs for XAMPP, www for WAMP, etc.)
cd /path/to/webroot
# Extract project files
unzip exam-timetable-system.zip
```

### Step 2: Database Setup
```bash
# Open phpMyAdmin or MySQL console
mysql -u root -p

# Database will be created automatically on first access
# Or manually create:
CREATE DATABASE exam_timetable;
USE exam_timetable;
```

### Step 3: Configure Database Connection
Edit `/includes/db_config.php`:
```php
define('DB_SERVER', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_password');  // Set your MySQL password
define('DB_NAME', 'exam_timetable');
```

### Step 4: Initialize Database
Access in browser:
```
http://localhost/M1PROJET/includes/init_db.php
```
This will create all necessary tables automatically.

### Step 5: Create Demo Data
Use the Admin dashboard to:
1. Create departments
2. Create formations for each department
3. Add modules to formations
4. Register students and professors
5. Add exam rooms
6. Assign professors to modules
7. Enroll students in modules

### Step 6: Generate Schedule
1. Login as Admin
2. Go to "Schedule Exams"
3. Click "Generate Schedule"
4. System will automatically create exams respecting all constraints

## 🔐 Default Test Accounts

After setup, create accounts via signup or use admin tools:

**Admin Account** (Create via signup with role=admin):
- Email: admin@university.edu
- Password: admin123

**Professor Account** (Create via Admin > Professors):
- Email: prof@university.edu
- Password: professor123

**Student Account** (Create via Admin > Students):
- Email: student@university.edu
- Password: student123

## 📁 Project Structure

```
M1PROJET/
├── auth/
│   ├── login.php          # Login page
│   ├── signup.php         # User registration
│   └── logout.php         # Logout handler
├── admin/
│   ├── dashboard.php      # Admin main dashboard
│   ├── departments.php    # Manage departments
│   ├── formations.php     # Manage formations
│   ├── modules.php        # Manage modules
│   ├── professors.php     # Manage professors
│   ├── students.php       # Manage students
│   ├── rooms.php          # Manage exam rooms
│   ├── exams.php          # View scheduled exams
│   ├── scheduling.php     # Generate exam schedule
│   ├── conflicts.php      # Manage conflicts
│   └── users.php          # Manage all users
├── student/
│   ├── dashboard.php      # Student timetable
│   └── modules.php        # Student's modules
├── prof/
│   ├── dashboard.php      # Professor dashboard
│   ├── exams.php          # Professor's exams
│   ├── surveillances.php  # Supervision assignments
│   └── modules.php        # Teaching modules
├── doyen/
│   ├── dashboard.php      # Doyen main dashboard
│   ├── exams.php          # All exams view
│   ├── conflicts.php      # Global conflicts
│   └── statistics.php     # System statistics
├── chef/
│   ├── dashboard.php      # Department head dashboard
│   ├── exams.php          # Department exams
│   ├── formations.php     # Department formations
│   └── conflicts.php      # Department conflicts
├── includes/
│   ├── db_config.php      # Database configuration
│   ├── init_db.php        # Database initialization
│   ├── session.php        # Session management
│   └── functions.php      # Utility functions
├── css/
│   └── style.css          # Global styles
├── js/
│   └── (additional scripts if needed)
├── index.php              # Home page redirect
└── README.md              # This file
```

## 🔌 API Functions

All reusable functions are in `/includes/functions.php`:

```php
// Authentication
hash_password($password)
verify_password($password, $hash)
get_user_by_email($email)
create_user($email, $password, $role, $full_name, $phone)

// Data Retrieval
get_professor_by_user_id($user_id)
get_student_by_user_id($user_id)
get_student_modules($student_id)
get_professor_modules($prof_id)
get_student_exams($student_id)
get_professor_exams($prof_id)
get_professor_surveillances($prof_id)

// Constraint Checking
check_student_exam_conflicts($student_id)
check_professor_overload($prof_id)
check_room_capacity_violations($exam_id)

// Conflict Management
get_all_conflicts()
get_conflicts_by_department($department_id)
record_conflict($exam_id, $conflict_type, $description, $severity)

// Statistics
get_statistics()
get_department_statistics($department_id)
```

## 🛡️ Security Features

- **Password Hashing**: bcrypt algorithm (PASSWORD_BCRYPT)
- **SQL Injection Prevention**: Prepared statements with parameter binding
- **Session Security**: PHP session-based authentication
- **Role-Based Access Control**: Server-side permission verification
- **XSS Prevention**: htmlspecialchars() for output encoding
- **CSRF Protection**: Server-side session validation

## 📊 Scheduling Algorithm

The automatic scheduling system:
1. Retrieves all modules with enrolled students
2. Sorts modules by formation and ID
3. Allocates rooms based on student count (capacity matching)
4. Distributes exams across multiple days
5. Uses 4 time slots per day
6. Assigns professors for supervision
7. Detects and flags all conflicts
8. Records conflicts for admin review

## 🐛 Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check username/password in `db_config.php`
- Ensure database exists or let auto-creation run

### Tables Not Created
- Access `/includes/init_db.php` in browser
- Check MySQL user permissions
- Review error messages in browser console

### Login Issues
- Verify user account exists in database
- Check password is correct
- Clear browser cookies and try again

### Scheduling Conflicts
- Review conflicts in Admin > Conflicts
- Mark as resolved once fixed
- Re-run schedule generation if needed

## 📈 Performance Considerations

- Database queries use prepared statements for security
- Sidebar navigation requires JavaScript for toggle (responsive)
- Large datasets (1000+ exams) may need pagination
- Consider adding indexes on frequently queried columns:
  ```sql
  ALTER TABLE examens ADD INDEX (exam_date);
  ALTER TABLE examens ADD INDEX (module_id);
  ALTER TABLE inscriptions ADD INDEX (student_id);
  ```

## 🎓 Academic Features

- **Semester Planning**: Track modules by semester
- **Credit Hours**: Record module credit allocation
- **Formation Management**: Organize programs by department
- **Student Tracking**: Complete enrollment management
- **Professor Assignment**: Link professors to modules
- **Room Management**: Track capacity and room types (classroom, amphi, lab)

## 📝 License

This project is for educational purposes in academic institution management.

## 👨‍💼 Support

For issues or questions:
1. Check README and documentation
2. Review error messages in database logs
3. Verify all files are uploaded correctly
4. Test with fresh database initialization

---

**Version**: 1.0  
**Last Updated**: January 2026  
**Built With**: HTML5, CSS3, Vanilla JavaScript, PHP, MySQL
