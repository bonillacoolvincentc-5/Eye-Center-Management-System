# Eye-Center-Management-System

System Installation Guide (via XAMPP)
To install and deploy Pangasinan Eye Center Management System, follow these steps carefully:
1. Install XAMPP
•	Download the latest version of XAMPP from the official Apache Friends website.
•	Run the installer and select the following components: Apache, MySQL, PHP, phpMyAdmin.
•	Complete the installation and launch the XAMPP Control Panel.
2. Start Apache and MySQL
•	Open the XAMPP Control Panel.
•	Click Start for Apache and MySQL
•	Ensure both modules are running (green indicators).
3. Copy System Files
•	Locate the project folder named Eye_Center_Management_System.
•	Copy the folder and paste it into the htdocs directory of XAMPP.
o	Example: C:\xampp\htdocs\eye_center_management_system.
4. Import the Database
•	Open a browser and go to: http://localhost/phpmyadmin.
•	Create a new database named ims/ams.
•	Click Import, choose the file ims/ams.sql, and execute the import.
5. Access the System
•	Open a browser and go to: http://localhost/eye_center_management_system/index.html
•	The login page of the SmartEnroll Portal will appear.
________________________________________
Default Login Credentials (for testing)
•	Administrator:
o	Email: pangasinaneyecenterph@gmail.com
o	Password: Admin321
•	Doctor:
o	Email: doctor1@gmail.com
o	Password: Doctor#01

•	Patient:
o	Email: PatientOne@gmail.com
o	Password: Patient#01
(Note: Change default passwords immediately after first login.)
________________________________________
User Roles and Functionalities
1. Patient
•	Login using patient credentials.
•	Book Appointment – book an appointment.
•	View Appointment Schedules/Schedules History – check daily schedules and bookings history.
•	Profile/Information Update – Update Informations and Account password.
2. Doctor
•	Login using doctor credentials.
•	Set Schedule– Set own schedules and availability.
•	View Daily Appointments/Cancelled Appointments – Check Daily Scheduled and Cancelled Appointments
•	Profile/Information Update – Update Informations and Account password.
3. Administrator
•	Login using administrator credentials.
•	Manipulate Doctor/Services – add, edit, and remove doctor accounts and services they offer.
•	Generate Booking Statistic Reports – create summaries of booking statistics.
•	Set Doctor Schedules – set a schedule for a doctor.
•	Manipulate Appointments and Show History – Can Approve/Reject Appointments, Show Completed Appointments and Manipulate Appointments Progress such as Ongoing and Completed
•	Block Patients Account – Can block patients account to avoid spamming
•	Manage Inventory – Manage inventory products such as update, edit, and stock in. Can also see used products in an appointment

System Requirements
Minimum Requirements
•	Processor: Intel Core i3 or equivalent
•	RAM: 4GB
•	Storage: 500MB free space
•	Operating System: Windows 10 or higher
•	Browser: Google Chrome (latest version recommended)

Recommended Requirements
•	Processor: Intel Core i5 or higher
•	RAM: 8GB
•	Storage: 1GB free space
•	Operating System: Windows 10/11
•	Browser: Google Chrome, Mozilla Firefox (latest versions)
________________________________________
Maintenance and Backup
1.	Database Backup
o	Open phpMyAdmin → Select ims/ams → Click Export → Save the .sql file.
o	Keep backups in a secure location.
2.	System Update
o	Replace files in htdocs/eye_center_management_system when updates are provided.
o	Re-import updated SQL scripts if database changes are included.
3.	Password Security
o	Advise all users to change passwords regularly.
o	Use strong passwords with letters, numbers, and symbols.

Credits to Githb:@HashenUdara for the UI design and logics. This is the improvised version of the system.
Visit @HashenUdara - edoc-doctor-appointment-system here in Github
