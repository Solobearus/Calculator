

<form action="">
  input:<br>
  <input type="text" name="input" value="<?php if(isset($_GET['input'])){echo$_GET['input'];}?>"/>
  <br><br>
  <input type="submit" value="Calculate">
</form> 


<?php

include 'utils/Calculator.php';
if(isset($_GET["input"])){
    $post = htmlspecialchars($_GET["input"]);

    $calc = new Calculator($post);
}

