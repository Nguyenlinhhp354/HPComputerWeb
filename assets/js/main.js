document.addEventListener("DOMContentLoaded", function () {
  const toggleButton = document.getElementById("sidebarToggle");
  const adminLayout = document.querySelector(".admin-layout");

  if (!toggleButton || !adminLayout) {
    return;
  }

  toggleButton.addEventListener("click", function () {
    if (window.innerWidth <= 768) {
      adminLayout.classList.toggle("mobile-sidebar-open");
    } else {
      adminLayout.classList.toggle("sidebar-collapsed");
    }
  });

  // Đóng menu khi bấm ra ngoài trên màn hình nhỏ
  document.addEventListener("click", function (event) {
    if (
      window.innerWidth <= 768 &&
      adminLayout.classList.contains("mobile-sidebar-open") &&
      !event.target.closest(".sidebar") &&
      !event.target.closest("#sidebarToggle")
    ) {
      adminLayout.classList.remove("mobile-sidebar-open");
    }
  });
});
