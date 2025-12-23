# 🛒 SubCart - Professional E-Commerce Platform

A full-featured e-commerce web application built with PHP, MySQL, and modern web technologies.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat&logo=javascript&logoColor=black)
![CSS3](https://img.shields.io/badge/CSS3-Modern-1572B6?style=flat&logo=css3&logoColor=white)

## ✨ Features

### 🛍️ Customer Experience
- **Modern Shopping Cart** - Add/remove items, update quantities (works for guests and logged-in users)
- **Advanced Product Search** - Filter by category, brand, and price range
- **Responsive Design** - Mobile-friendly interface with elegant sidebar navigation
- **User Authentication** - Secure registration, login, and session management
- **Order Processing** - Complete checkout with order tracking

### 👑 Admin Management
- **Elegant Admin Panel** - Professional dashboard with sophisticated design
- **Product Management** - Create, edit, delete products with image uploads
- **Category & Brand Management** - Organize inventory efficiently
- **User Management** - View and manage customer accounts
- **Order Management** - Process and track customer orders

### 🔧 Technical Excellence
- **Clean Architecture** - MVC pattern with proper separation of concerns
- **Security Features** - SQL injection protection, input validation, role-based access
- **Professional Code** - Well-documented, maintainable codebase
- **Database Integrity** - Proper relationships, constraints, and data validation

## 🚀 Technology Stack

- **Backend**: PHP 8.x with Object-Oriented Programming
- **Database**: MySQL with prepared statements and foreign key constraints
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Custom CSS with modern design system and glassmorphism effects
- **Icons**: Font Awesome 6.4.0
- **AJAX**: jQuery for dynamic interactions

## 📁 Project Structure

```
├── admin/                  # Admin panel pages
├── actions/               # AJAX action handlers (29 endpoints)
├── classes/               # PHP classes (MVC model layer)
├── controllers/           # Business logic controllers
├── css/                   # Stylesheets with modern design system
├── js/                    # JavaScript files and interactions
├── login/                 # Authentication pages
├── settings/              # Configuration and core functions
├── uploads/               # User uploaded files
├── images/                # Static assets
├── index.php              # Homepage
├── all_product.php        # Product catalog
├── cart.php               # Shopping cart
├── checkout.php           # Order processing
└── single_product.php     # Product details
```

## 🎯 Key Highlights

### 🎨 Modern UI/UX
- **Elegant Design** - Professional corporate-grade styling
- **Responsive Layout** - Perfect on desktop, tablet, and mobile
- **Intuitive Navigation** - Clean sidebar with smooth animations
- **Visual Feedback** - Loading states, success messages, error handling

### 🔐 Security & Performance
- **Role-Based Access** - Clean Admin/Customer permission system
- **Input Validation** - Comprehensive sanitization and validation
- **Session Security** - Secure session management with regeneration
- **Optimized Queries** - Efficient database operations

### 📊 Sample Data Included
- **6 Users** - 1 admin + 5 customers ready for testing
- **10 Products** - Sample inventory across 5 categories
- **5 Categories** - Electronics, Clothing, Books, Home & Garden, Sports
- **6 Brands** - Apple, Samsung, Nike, Adidas, Penguin Books, IKEA

## 🛠️ Installation & Setup

### Prerequisites
- PHP 8.x or higher
- MySQL 5.7+ or MariaDB
- Web server (Apache/Nginx)
- Modern web browser

### Quick Start
1. **Clone the repository**
   ```bash
   git clone https://github.com/oforias/subcart-ecommerce.git
   cd subcart-ecommerce
   ```

2. **Database Setup**
   - Create a MySQL database
   - Import the database schema (contact for SQL file)
   - Update database credentials in `settings/db_cred.php`

3. **Web Server Configuration**
   - Point document root to project folder
   - Ensure `uploads/` directory has write permissions (755)
   - Enable required PHP extensions: mysqli, gd, fileinfo

4. **Access the Application**
   - Visit your domain in a web browser
   - Admin login: `admin@example.com` / `admin123`

## 🎮 Demo Credentials

### Admin Access
- **Email**: `admin@example.com`
- **Password**: `admin123`
- **Features**: Full system management, elegant admin panel

### Sample Customers
- `alan.k.ofori@gmail.com`
- `mmalebna@gmail.com`
- `test1@example.com`
- `test2@example.com`
- `demo@test.com`

## 🌟 Screenshots

*Professional e-commerce platform with modern design and full functionality*

## 🚀 Deployment

### Production Deployment
- Compatible with shared hosting (InfinityFree, etc.)
- Minimal server requirements
- Easy configuration management
- Professional-grade security

### Development
- Clean codebase for easy customization
- Modular architecture for feature additions
- Comprehensive error handling
- Developer-friendly structure

## 🤝 Contributing

This is a portfolio project showcasing full-stack PHP development skills. Feel free to explore the code and provide feedback!

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 👨‍💻 Developer

**Samuel Amoako Oforias**
- GitHub: [@oforias](https://github.com/oforias)
- Portfolio: Professional PHP/MySQL Developer

---

### 🎯 Perfect For
- **Portfolio Projects** - Showcase full-stack development skills
- **Learning Resource** - Study modern PHP/MySQL architecture
- **Base Template** - Extend for real e-commerce needs
- **Academic Projects** - Professional-quality coursework

**Built with ❤️ using PHP, MySQL, and modern web technologies**