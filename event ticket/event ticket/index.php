<?php
require_once 'config.php';

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    redirectBasedOnRole();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Ticket Booking and Reservation System</title>       
          <style>
            *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        header{
            display: flex;
            justify-content: space-between;
            background: #0c18fd;
            padding: 10px;
        }
        body{
            height: 100vh;
            width: 100vw;
            background: #e3e9e4;
        }
        .logo{
            color: #fff;
            text-decoration: none;
            margin-left: 20px;
        }
        nav{
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        nav a{
            text-decoration: none;
            color: #fff;
            font-size: 1.5rem;
            margin: 0 30px 0 0;
        }
        nav a:hover, ul li a:hover, .con a:hover{
            text-decoration: underline;
            color: #fbac0d;
        }
        main{
            margin: 10px;

        }
        section{
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-family: cursive;
            padding: 50px;
            box-shadow:0 0 25px #b5bcb6ff;
            min-height: 90vh;
            margin: 15px;
        }
        .home, .about, .contact{
            display: flex;
            justify-content: center;
            flex-direction: column;
            gap: 30px;
            padding: 50px;
            box-shadow:0 0 25px #d4e0d6;
            margin: 15px;
            transition: linear 0.4s ease-in-out;
        }
        .home{
            color: #fff;
            background: #0907073e;
            background-image: url("aaa.png");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-blend-mode: overlay;
            transition: background-image 0.4s ease-in-out;


        }
       
        h1{
            color: #1c0dfb;
        }
        .links{
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .links .get{
            text-decoration: none;
            color: #fff;
            background: #dc8708;
            padding: 7px 15px;
            border-radius: 12px;
        }
        .links .get:hover{
            color: #fff;
            background: #1ce43e;
            box-shadow: 0 0 25px #1ce43e;
           
        }
        .links .learn{
            background: #11d4f7;   
        }
        .contact form{
            display: flex;
            justify-content: space-between;
            flex-direction: column;
            margin: auto;
        }
        .contact form input{
            background: transparent;
            border: none;
            outline: none;
            border-bottom: 3px solid #c1bfbf;
            padding: 10px 10px;
            font-family: cursive;
            
        }
        .contact form input:focus{
            border: none;
            outline: none;
            border-bottom: 3px solid #1c0dfb;
            color: #1c0dfb;

        }
        .contact form textarea{
            background: transparent;
            outline: none;
            border: 3px solid #c1bfbf;
            padding: 10px 10px;
            font-family: cursive;
            
        }
        .contact form textarea:focus{
            border: none;
            outline: none;
            border: 3px solid #1c0dfb;
            color: #1c0dfb;

        }
        #sub{
            border-bottom: none;
            border:2px solid #1c0dfb;
            color: #1c0dfb;
            border-radius: 20px;
            background: transparent;
        }
        #sub:hover{
            border-bottom: none;
            background: #1c0dfb;
            color: #fff;
            box-shadow: 0 0 25px #1c0dfb;
        }

        footer{
            width: 100%;
            justify-content: center;
            flex-direction: column;
            gap: 30px;
            padding: 50px;
            box-shadow:0 0 25px #d4e0d6;
            margin: 15px;
            background: #03146f;
            opacity: 0.9;
        }
        .footer{
            width: 100%;
            display: flex;
            justify-content: space-between;
            gap: 30px;
            padding: 50px;
            margin: 15px;
            background: #03146f;
            opacity: 0.9;
        } 
        .footers{
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        footer h3{
            color: #fff;
        }
        footer p{
            color: #1ce43e;
        }
        ul{
            list-style: none;
        }
        ul li a{
            text-decoration: none;
            color: #97af9b;
            padding-left: 30px;
            
        }
        .con a{
            text-decoration: none;
            color: #97af9b;            
        }
        .scrol{
            display: flex;
            justify-content: center;
            margin: 0 60px;
            gap: 10px;
        }
        .item{
            width: 120px;
        }
        .item img{
            width: 100px;
            border-radius: 12px;
        }
        .item img:hover{
            transform: translateY(-10px);
            width: 120px;
            position: absolute;
        }
        #footer{
            min-height: 50vh;
        }
       
          </style>
</head>
<body>
      <header>
    <div class="logo">
        <h2>G5 Event</h2>
    </div>
    
    <nav>
        <a href="#home" >Home</a>
        <a href="#about" >About Us</a>
        <a href="#contact" >Contact Us</a>
        <a href="login.php" >Login</a>
    </nav>
    </header>
    <main>
        <section id="home">
            <div class="home">
            <h1>Wellcome To Grand Event</h1>
            <h3>Book Events. <span style="color: #c29249;">Reserve Seats.</span> Create Memories.</h3>
            <hr>
            <p>Welcome to our Event Ticket Booking and 
                Reservation System, your one-stop platform for discovering events, booking tickets, and reserving seats with ease. From concerts and conferences to sports and cultural events,
                 we make ticketing simple, fast, and secure.</p>
                 <div class="links">
                    <a href="register.php" class="get">Get Started</a>
                    <a href="#about" class="get learn">Learn More</a>
                 </div>
                 </div>
                 <div class="scrol">
                    <div class="item">
                        <a href="#"><img src="aaa.png" data-bg="aaa.png" alt=""></a>
                    </div>
                    <div class="item">
                        <a href="#"><img src="a.png" data-bg="a.png" alt=""></a>
                    </div>
                    <div class="item">
                        <a href="#"><img src="b.png" data-bg="b.png" alt=""></a>
                    </div>
                    <div class="item">
                        <a href="#"><img src="c.png" data-bg="c.png" alt=""></a>
                    </div>
                    <div class="item">
                        <a href="#"><img src="d.png" data-bg="d.png" alt=""></a>
                    </div>
                    <div class="item">
                        <a href="#"><img src="e.png" data-bg="e.png" alt=""></a>
                    </div>
                    
                 </div>
        </section>
        <section id="about">
            <div class="about">
                <h1>About Us</h1>
                <hr>
                <p>Our Event Ticket Booking and Reservation System is
                     designed to simplify how people find, book, and
                      attend events. We connect event organizers and attendees
                       through a  reliable, efficient, and secure digital platform.</p>
                       
                      <h3>Our Mission</h3> 
                      <p>To provide a seamless and trustworthy ticket booking experience that saves time, reduces effort,
                         and enhances event participation</p>
                         <h3>Our Vision</h3>
                         <p>To become a leading digital ticketing platform recognized for reliability, innovation, and customer satisfaction.</p>
            </div>
        </section>
        <section id="contact">
            <h1>Contact Us</h1>
            <hr>
            <p>Have questions, need support, or want to partner with us? We’re here to help.</p>
            <div class="contact">
        <form action="">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" placeholder="Enter your name"><br>
            <label for="email">Email</label>
            <input type="text" name="email" id="email" placeholder="Enter your email"><br>
             <label for="massage">Message</label>
             <textarea name="massage" id="massage" cols="60" rows="15"placeholder="Enter your message here..."></textarea><br>
             <div class="sub">
             <input type="submit" class="sub" id="sub" name="submit" value="Send Message" >
             </div>
        </form>
        </div>
        </section>
        <section id="footer">
            <div class="footers">
            <footer>
                <div class="footer">
                <div class="head">
                <h3>Event Ticket Booking & Reservation System</h3>
                <p>Book tickets, reserve seats, and enjoy events with ease.</p><br>
                
                </div>
                <div class="quick">
                    <h3>quick Links</h3>
                    <ul>
                        <li><a href="#home" >Home</a></li>
                        <li><a href="#about" >About Us</a></li>
                        <li><a href="#contact" >Contact Us</a></li>
                    </ul>
                </div>
                <div class="con">
                    <p>Contact: <br><a href="mailto:xhavirh@gmail.com">xhavirh@gmail.com</a> <br> <a href="tel:+251943430102">+251943430102</a></p>
                </div>
                </div>
                <div class="fot">
                    <p style="text-align:center;">&copy; 2026 Event Ticket Booking & Reservation System. All rights reserved.</p>
                </div>
            </footer>
        </div>
        </section>
    </main>
    
    <script>
    const home = document.querySelector('.home');
    const images = document.querySelectorAll('.scrol img');

    images.forEach(img => {
        img.addEventListener('click', () => {
            const bg = img.getAttribute('data-bg');
            home.style.backgroundImage = `url('${bg}')`;
        });
    });
</script>


</body>
</html>