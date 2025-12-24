Nexus Chat ⚡

Nexus Chat is a lightweight, responsive, and secure real-time messaging application. It features a modern dark-mode UI, support for direct messages and private groups, media sharing (images/videos), and message encryption.

🚀 Features
 * Real-time Messaging: Messages update instantly via optimized polling.
 * Direct Messages (DM): Private 1-on-1 conversations with read receipts (blue ticks).
 * Group Chats: Password-protected groups for communities or teams.
 * Rich Media Support:
   * Images & Videos.
   * Files: Support for generic document attachments.
 * Unread message badges and counters for every chat.
 * Security: Messages are encrypted using AES-128 before storage.
 * Responsive UI: Fully responsive design using Tailwind CSS, optimized for mobile and desktop.
 
🛠️ Tech Stack
 * Frontend: HTML5, JavaScript (ES6+), Tailwind CSS (CDN), Lucide Icons.
 * Backend: PHP (Native).
 * Database: MySQL.
 * Encryption: AES-128-ECB (OpenSSL).
 
⚙️ Installation & Setup
1. Clone the Repository
git clone [https://github.com/ayush-024/nexus-chat.git](https://github.com/ayush-024/nexus-chat.git)
cd nexus-chat

2. Database Setup
Create a MySQL database and import the db.sql file.

3. Backend Configuration
Modify the file named db.php and add your database credentials:

5. Run the Application
 * Place the project folder in your web server's root directory (e.g., htdocs for XAMPP or /var/www/html for Apache).
 * Open index.html and ensure the USE_PHP constant is set to true.
 * Access the app via your browser (e.g., http://localhost/nexus-chat).

   
🗂️ Project Structure 

	nexus-chat/ 
	├── index.html   # Main frontend 
	├── api.php 	 # Handles all API 
	├── db.php 	  # Database connect 
	├── uploads/ 	# storage 
	└── README.md    # doc
	
🔐 Security Note This application uses AES-128-ECB for message encryption. While this provides a layer of privacy for a portfolio or personal project, ECB mode is generally not recommended for high-security enterprise applications. For production environments, consider upgrading to AES-256-GCM.

🤝 Contributing Contributions and feature requests are welcome! Feel free to check the <a href="https://github.com/ayush-024/nexus-chat/issues/new">issues page</a>.

📩 Contact me
<a href="https://t.me/ayushpratap24">Ayush Singh</a>
