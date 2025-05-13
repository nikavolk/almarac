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
        Omejitev velikosti: 5 MB

    Amazon Rekognition
        Avtomatska kategorizacija in označevanje (tagging) slik in videov po uploadu

    Amazon CloudWatch
        Logiranje aktivnosti in spremljanje delovanja sistema

Cilji

    Prikaz sodobne DevOps arhitekture v oblaku
    Uporaba AI za izboljšanje uporabniške izkušnje
