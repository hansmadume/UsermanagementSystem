
const password = document.getElementById("password");
const showPassword = document.getElementById("showPassword");

if (showPassword && password) {
    showPassword.addEventListener("change", function () {
        password.type = this.checked ? "text" : "password";
    });
}
