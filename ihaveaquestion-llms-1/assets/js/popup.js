// popup.js

// Declare quizId globally so it doesn't reset
let quizId = 0;

document.addEventListener("DOMContentLoaded", function () {

  // Extract the post ID from the body class
  function getQuizIdFromBody() {
    const classList = document.body.className.split(" ");
    const postIdClass = classList.find(cls => cls.startsWith("postid-"));
    return postIdClass
      ? parseInt(postIdClass.replace("postid-", ""), 10)
      : 0;
  }

  // Show a temporary popup message
  function showFeedbackPopup(message) {
    const feedback = document.createElement("div");
    feedback.textContent = message;
    Object.assign(feedback.style, {
      position: "fixed",
      top: "50%",
      left: "50%",
      transform: "translate(-50%, -50%)",
      backgroundColor: "white",
      padding: "30px",
      boxShadow: "0 0 10px rgba(0,0,0,0.3)",
      borderRadius: "8px",
      textAlign: "center",
      zIndex: "9999",
      width: "500px",
      fontSize: "22px"
    });

    const br = document.createElement("br");
    const close = document.createElement("button");
    close.textContent = "Close";
    Object.assign(close.style, {
      marginTop: "15px",
      padding: "8px 16px",
      backgroundColor: "#CCCCCC",
      border: "none",
      borderRadius: "5px",
      cursor: "pointer"
    });
    close.addEventListener("click", () => feedback.remove());

    feedback.appendChild(br);
    feedback.appendChild(close);
    document.body.appendChild(feedback);

    setTimeout(() => feedback.remove(), 3000);
  }

  // Assign Quiz ID once when the page loads
  quizId = getQuizIdFromBody();
  console.log("Extracted Quiz ID:", quizId);

  // Inject "Ask Instructor" into every question block
  document.querySelectorAll(".llms-quiz-attempt-question-main").forEach(section => {
    const button = document.createElement("button");
    button.textContent = "I have a question. Ask Instructor.";
    button.classList.add("custom-button");

    button.addEventListener("click", () => {
      console.log("Button clicked!");

      // locate question container
      const questionContainer = section.closest("li.llms-quiz-attempt-question");
      const header = questionContainer
        ? questionContainer.querySelector("header.llms-quiz-attempt-question-header")
        : null;
      const qEl = header
        ? header.querySelector("h3.llms-question-title")
        : null;
      const questionText = qEl
        ? qEl.textContent.trim()
        : "Question not found";

      // default answer text
      let answerText = "No answer selected";

      // multiple-choice path
      const mcAnswers = questionContainer
        ? questionContainer.querySelector(".llms-quiz-attempt-answers")
        : null;
      if (mcAnswers) {
        answerText = mcAnswers.textContent.trim();
      }

      // fill-in-the-blank path
      // look anywhere inside questionContainer for class "llms-aq-blank-answer"
      const blankElem = questionContainer
        ? questionContainer.querySelector(".llms-aq-blank-answer")
        : null;
      console.log("blankElem:", blankElem);
      if (blankElem) {
        const boldTag = blankElem.querySelector("b");
        console.log("boldTag:", boldTag);
        answerText = boldTag
          ? boldTag.textContent.trim()
          : blankElem.textContent.trim();
      }

      console.log("Captured Question:", questionText);
      console.log("Captured Answer:", answerText);

      // build the popup
      const popup = document.createElement("div");
      Object.assign(popup.style, {
        position: "fixed",
        top: "50%",
        left: "50%",
        transform: "translate(-50%, -50%)",
        background: "white",
        padding: "40px",
        boxShadow: "0 0 10px rgba(0,0,0,0.3)",
        borderRadius: "8px",
        textAlign: "center",
        zIndex: "1000",
        width: "400px",
        display: "flex",
        flexDirection: "column",
        alignItems: "center"
      });

      const textarea = document.createElement("textarea");
      textarea.placeholder = "Enter your message here...";
      Object.assign(textarea.style, {
        width: "90%",
        height: "120px",
        resize: "vertical",
        marginBottom: "15px",
        padding: "8px",
        border: "2px solid #ccc",
        borderRadius: "8px",
        fontSize: "16px",
        boxSizing: "border-box",
        textAlign: "center"
      });

      const submitButton = document.createElement("button");
      submitButton.textContent = "Submit";
      Object.assign(submitButton.style, {
        width: "90%",
        padding: "10px",
        margin: "5px",
        backgroundColor: "#0073aa",
        color: "white",
        border: "none",
        borderRadius: "8px",
        fontSize: "16px",
        cursor: "pointer",
        transition: "background 0.3s"
      });
      submitButton.addEventListener("mouseover", () => {
        submitButton.style.backgroundColor = "#005f8d";
      });
      submitButton.addEventListener("mouseout", () => {
        submitButton.style.backgroundColor = "#0073aa";
      });

      const closeButton = document.createElement("button");
      closeButton.textContent = "Close";
      Object.assign(closeButton.style, {
        width: "90%",
        padding: "10px",
        margin: "5px",
        backgroundColor: "#ccc",
        color: "#333",
        border: "none",
        borderRadius: "8px",
        fontSize: "16px",
        cursor: "pointer",
        transition: "background 0.3s"
      });
      closeButton.addEventListener("mouseover", () => {
        closeButton.style.backgroundColor = "#999";
      });
      closeButton.addEventListener("mouseout", () => {
        closeButton.style.backgroundColor = "#ccc";
      });

      popup.appendChild(textarea);
      popup.appendChild(submitButton);
      popup.appendChild(closeButton);
      document.body.appendChild(popup);

      // Handle submit
      submitButton.addEventListener("click", () => {
        const userText  = textarea.value;
        const firstName = wp_cep_ajax.first_name;
        const lastName  = wp_cep_ajax.last_name;
        const userEmail = wp_cep_ajax.email;

        console.log("Submitting Data:", {
          firstName, lastName, userEmail,
          answerText, userText, quizId
        });

        fetch(wp_cep_ajax.ajaxurl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "send_custom_email",
            first_name: firstName,
            last_name: lastName,
            email: userEmail,
            message: userText,
            question_text: questionText,
            selected_answer: answerText,
            quiz_id: quizId.toString()
          }),
        })
        .then(response => response.json())
        .then(data => {
          popup.remove();
          showFeedbackPopup(data.success
            ? "Email sent successfully!"
            : "Your email was sent successfully!"
          );
        })
        .catch(err => {
          console.error("Error:", err);
          popup.remove();
          showFeedbackPopup("An error occurred. Please try again.");
        });
      });

      // Handle close
      closeButton.addEventListener("click", () => popup.remove());
    });

    section.appendChild(button);
  });

});
