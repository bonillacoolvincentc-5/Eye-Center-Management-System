
    <?php
    
    

    //import database
    include("../connection.php");



    if($_POST){
        //print_r($_POST);
        // Get id and email early for redirects and validation
        $id = $_POST['id00'] ?? '';
        $oldemail = $_POST["oldemail"] ?? '';
        
        // Accept name parts and normalize
        $first = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        
        // Validate required name parts
        if ($first === '' || $last === '') {
            $error = '2';
            header("location: doctors.php?action=edit&error=".$error."&id=".$id);
            exit();
        }
        
        $nic = $_POST['nic'] ?? '';
        $spec = $_POST['spec'] ?? '';
        $email = $_POST['email'] ?? '';
        $tele = $_POST['Tele'] ?? '';
        $password = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';
        
        // Allow empty passwords (no change) or matching passwords
        if (empty($password) || $password==$cpassword){
            $error='3';
            $result= $database->query("select tbl_doctor.docid from tbl_doctor inner join tbl_webuser on tbl_doctor.docemail=tbl_webuser.email where tbl_webuser.email='$email';");
            //$resultqq= $database->query("select * from tbl_doctor where docid='$id';");
            if($result->num_rows==1){
                $id2=$result->fetch_assoc()["docid"];
            }else{
                $id2=$id;
            }
            
            if($id2!=$id){
                $error='1';
                //$resultqq1= $database->query("select * from doctor where docemail='$email';");
                //$did= $resultqq1->fetch_assoc()["docid"];
                //if($resultqq1->num_rows==1){
                    
            }else{
                // Ensure NIC uniqueness (cannot match another doctor's NIC)
                $nicStmt = $database->prepare("SELECT docid FROM tbl_doctor WHERE docnic = ? AND docid <> ? LIMIT 1");
                $nicStmt->bind_param("si", $nic, $id);
                $nicStmt->execute();
                $nicRes = $nicStmt->get_result();
                if ($nicRes && $nicRes->num_rows > 0) {
                    $error = 'nic_exists';
                } else {
                // Build update query - only update password if provided
                if (!empty($password)) {
                    // Hash the password before updating
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql1="update tbl_doctor set docemail='$email',docpassword='$hashed_password',docnic='$nic',doctel='$tele',specialties=$spec,first_name='$first',middle_name='$middle',last_name='$last',suffix='$suffix' where docid=$id ;";
                } else {
                    // Update without changing password
                    $sql1="update tbl_doctor set docemail='$email',docnic='$nic',doctel='$tele',specialties=$spec,first_name='$first',middle_name='$middle',last_name='$last',suffix='$suffix' where docid=$id ;";
                }
                $database->query($sql1);
                
                $sql1="update tbl_webuser set email='$email' where email='$oldemail' ;";
                $database->query($sql1);
                //echo $sql1;
                //echo $sql2;
                $error= '5'; // Success code for update
                }
            }
            
        }else{
            $error='2';
        }
    
    
        
        
    }else{
        //header('location: signup.php');
        $error='3';
    }
    

    header("location: doctors.php?action=edit&error=".$error."&id=".$id);
    ?>
    
   

</body>
</html>