# File Manager Microservice

A microservice-based file manager that allows users to upload, download, and manage files using AWS S3 for storage. The application uses a distributed architecture with separate instances for web and database services.

## Features

- Upload files (up to 5MB)
- Download files via pre-signed URLs
- Delete files
- Modern UI with AlpineJS and TailwindCSS
- Progress bar for uploads
- Secure file storage in AWS S3
- Containerized with Docker
- Automatic deployment with GitHub Actions
- Distributed architecture with separate web and database instances

## Prerequisites

- AWS Account with S3 access
- Docker and Docker Compose
- PHP 8.2+
- MySQL 8.0+
- GitHub account (for automatic deployments)

## Local Development Setup

1. Clone the repository:

   ```bash
   git clone https://github.com/nikavolk/almarac.git
   cd file-manager
   ```

2. Copy the environment template:

   ```bash
   cp .env.example .env
   ```

3. Update the `.env` file with your credentials:

   ```
   # AWS Configuration
   AWS_ACCESS_KEY_ID=your_access_key_id
   AWS_SECRET_ACCESS_KEY=your_secret_access_key
   AWS_DEFAULT_REGION=your_region
   S3_BUCKET=your_bucket_name

   # Database Configuration (for web service)
   DB_HOST=localhost
   DB_NAME=filemanager
   DB_USER=filemanager
   DB_PASSWORD=your_secure_password

   # Database Configuration (for database service)
   MYSQL_ROOT_PASSWORD=your_secure_root_password
   MYSQL_USER=filemanager
   MYSQL_PASSWORD=your_secure_password
   ```

4. Start the services:

   ```bash
   # Start database service
   docker-compose -f docker-compose.db.yml up -d

   # Start web service
   docker-compose -f docker-compose.web.yml up -d
   ```

5. Initialize the database:
   ```bash
   docker-compose -f docker-compose.web.yml exec web php /var/www/html/schema.sql
   ```

The application should now be running at `http://localhost:80`

## Production Deployment

### AWS Infrastructure Setup

1. **VPC Setup**:

   - Create a new VPC
   - Create two subnets:
     - Public subnet (for web server)
     - Private subnet (for database)
   - Create an Internet Gateway (IGW) for the public subnet
   - Create a NAT Gateway for the private subnet
   - Configure route tables:
     - Public subnet -> IGW
     - Private subnet -> NAT Gateway

2. **Security Groups**:

   ```
   Web Server Security Group:
   - Inbound:
     - HTTP (80) from anywhere
     - HTTPS (443) from anywhere
     - SSH (22) from your IP
   - Outbound:
     - All traffic to anywhere

   Database Security Group:
   - Inbound:
     - MySQL (3306) from Web Server Security Group
   - Outbound:
     - All traffic to anywhere
   ```

3. **S3 Setup**:

   - Create an S3 bucket for file storage
   - Configure bucket policy for private access
   - Enable versioning (optional)
   - Configure lifecycle rules (optional)

4. **EC2 Instances**:

   ```
   Web Server Instance:
   - Launch in public subnet
   - Assign public IP
   - Use web server security group
   - Install Docker and Docker Compose
   - Deploy using docker-compose.web.yml

   Database Server Instance:
   - Launch in private subnet
   - No public IP
   - Use database security group
   - Install Docker and Docker Compose
   - Deploy using docker-compose.db.yml
   ```

5. **IAM Setup**:
   - Create an IAM user with S3 access
   - Create an ECR repository
   - Configure necessary permissions for GitHub Actions

### GitHub Actions Setup

Add the following secrets to your GitHub repository:

Web Server Configuration:

- `AWS_ACCESS_KEY_ID`: AWS access key for S3 and ECR
- `AWS_SECRET_ACCESS_KEY`: AWS secret key
- `AWS_REGION`: AWS region
- `S3_BUCKET`: S3 bucket name
- `WEB_HOST`: Public IP/hostname of web server
- `WEB_USERNAME`: SSH username for web server
- `WEB_SSH_KEY`: SSH private key for web server
- `DB_HOST`: Private IP of database server
- `DB_NAME`: Database name
- `DB_USER`: Database user
- `DB_PASSWORD`: Database password

Database Server Configuration:

- `DB_HOST`: Private IP of database server
- `DB_USERNAME`: SSH username for database server
- `DB_SSH_KEY`: SSH private key for database server
- `MYSQL_ROOT_PASSWORD`: MySQL root password
- `DB_NAME`: Database name
- `DB_USER`: Database user
- `DB_PASSWORD`: Database password

### Manual Deployment

1. **Database Server Setup**:

   ```bash
   # SSH into database server
   ssh -i private_key.pem user@db-private-ip

   # Clone repository
   git clone <repository-url>
   cd file-manager

   # Configure environment
   cp .env.example .env
   # Edit .env with database credentials

   # Start database service
   docker-compose -f docker-compose.db.yml up -d
   ```

2. **Web Server Setup**:

   ```bash
   # SSH into web server
   ssh -i private_key.pem user@web-public-ip

   # Clone repository
   git clone <repository-url>
   cd file-manager

   # Configure environment
   cp .env.example .env
   # Edit .env with AWS and database credentials

   # Start web service
   docker-compose -f docker-compose.web.yml up -d
   ```

## Security Considerations

- All files are stored privately in S3
- Downloads use pre-signed URLs that expire after 20 minutes
- Database is isolated in private subnet
- Web server in public subnet with minimal required ports open
- Database credentials are stored securely
- File size is limited to 5MB
- Input validation is implemented
- HTTPS should be configured in production
- Regular security updates should be applied to all instances
- Network access is restricted using security groups
- Use AWS Secrets Manager for production credentials (recommended)

## Monitoring and Maintenance

- Set up AWS CloudWatch for monitoring:
  - EC2 instance metrics
  - S3 bucket metrics
  - VPC flow logs
- Configure automated backups for:
  - Database
  - S3 bucket
  - EC2 instances
- Implement log aggregation
- Set up alerts for:
  - Instance health
  - Storage capacity
  - Error rates
  - Security events

## License

MIT

Projektna naloga: AWS Microservice z Docker, CI/CD in AI integracijami
Opis projekta

Projekt bo prikazal implementacijo osnovne mikroservisne arhitekture na AWS-u s sodobnimi praksami, tj. kontejnerizacija, avtomatiziran CI/CD, uporaba umetne inteligence in integracija različnih AWS storitev.
Arhitektura

    EC2 Instances
        Web Server (javno dostopna EC2): gosti PHP/Apache aplikacijo v Docker kontejnerju
        Database Server (zasebna EC2): gosti MySQL podatkovno bazo

    GitHub Actions
        Avtomatiziran deployment PHP aplikacije ob spremembah v Git repozitoriju

    Docker
        PHP/Apache aplikacija z Docker Compose konfiguracijo

Funkcionalnosti

    Upload datotek na S3
        Omejitev velikosti: 10 MB

    Amazon Rekognition
        Avtomatska kategorizacija in označevanje (tagging) slik in videov po uploadu

    AWS Lambda
        Avtomatski resize in konverzija slik po uploadu

    Amazon CloudWatch
        Logiranje aktivnosti in spremljanje delovanja sistema

    Amazon WorkDocs
        Pregledovanje in urejanje .doc datotek (v primeru, da je uploadana datoteka .doc)

Vzdrževanje podatkov

    MySQL backup
        Dnevni backup z mysqldump preko cron job
        Shranjevanje backup datotek na S3 z uporabo AWS CLI

Cilji

    Prikaz sodobne DevOps arhitekture v oblaku
    Uporaba AI za izboljšanje uporabniške izkušnje
    Prikaz varnostnega kopiranja podatkov
