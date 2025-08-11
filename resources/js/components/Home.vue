<template>

  <div class="container py-5">
    <h1 class="mb-4">💬 Ask AI</h1>
    <hr />
    <br />

    <!-- Loading Spinner -->
    <div id="loading-spinner" class="text-center mt-4" v-if="loading">
      <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2">Thinking...</p>
    </div>

    <!-- Form -->
    <form @submit.prevent="submitForm">
      <div class="mb-3">
        <label for="model" class="form-label">Choose a Model</label>
        <select v-model="model" id="model" class="form-select">
          <option v-for="(label, value) in models" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
      </div>

      <div class="mb-3">
        <label for="dataset" class="form-label">
          Provide Dataset <span class="text-muted">(Optional)</span>
        </label>
        <textarea v-model="dataset" id="dataset" rows="3" class="form-control"></textarea>
      </div>

      <div class="mb-3">
        <label for="prompt" class="form-label">Enter Your Prompt</label>
        <textarea v-model="prompt" id="prompt" rows="2" class="form-control" required></textarea>
      </div>

      <div class="form-check mb-3">
        <input v-model="useTestData" class="form-check-input" id="use_testdata_checkbox" type="checkbox" value="1" />
        <label class="form-check-label" for="use_testdata_checkbox">Query Test Data</label>
      </div>

      <router-link to="/" class="btn btn-outline-dark btn-sm text-decoration-none">Back</router-link>
      <button type="submit" class="btn btn-outline-success btn-sm mx-2" :disabled="loading">Submit</button>
    </form>

    <!-- Responses -->
    <div id="responses-container" class="mt-4">
      <div v-for="(response, index) in responses" :key="index" class="ai-response" v-html="response.html"></div>
    </div>
  </div>
</template>


<script setup>
// npm install highlight.js marked
import { ref, reactive, onMounted, nextTick } from "vue";
import hljs from "highlight.js";
import "highlight.js/styles/github-dark.css";
import { marked } from "marked";
import axios from "axios";

const loading = ref(false);
const model = ref("");
const dataset = ref("");
const prompt = ref("");
const useTestData = ref(false);

const models = reactive({
  "deepseek/deepseek-chat-v3-0324:free": "🧠 DeepSeek Chat V3 (0324)",
  "qwen/qwen3-coder:free": "💻 Qwen3 Coder",
  "mistralai/mistral-small-3.1-24b-instruct:free": "📏 Mistral Small 3.1 24B",
  "z-ai/glm-4.5-air:free": "🌬️ Z.AI GLM 4.5 Air",
});

const responses = ref([]);

onMounted(() => {
  model.value = Object.keys(models)[0]; // default first model
});

const submitForm = async () => {
  loading.value = true;
  try {
    const { data } = await axios.post("/ai/submit", {
      model: model.value,
      dataset: dataset.value,
      prompt: prompt.value,
      use_testdata_checkbox: useTestData.value ? 1 : 0,
    });

    let formattedMessage = marked.parse(data.message);
    formattedMessage = formattedMessage.replace(
      /<pre><code class="language-([^"]+)">/g,
      (match, lang) => `<pre><code class="language-${lang}">`
    );

    responses.value.unshift({
      html: `
        <strong>Prompt:</strong> ${data.prompt}
        <hr/>
        <strong>Response</strong> <span class='text-success'>[${data.model}]</span><strong>:</strong><br/>
        ${formattedMessage}
      `,
    });

    await nextTick();
    document.querySelectorAll("pre code").forEach((el) => {
      hljs.highlightElement(el);
    });

    dataset.value = "";
    prompt.value = "";
  } catch (error) {
    console.error(error);
    alert("An error occurred. Please try again.");
  } finally {
    loading.value = false;
  }
};
</script>


<style>
@import "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css";

.ai-response {
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  padding: 1rem;
  margin-bottom: 1rem;
  background-color: #f8f9fa;
}
.ai-response pre {
  background-color: #282c34;
  color: #abb2bf;
  padding: 1rem;
  border-radius: 0.25rem;
  overflow-x: auto;
}
.ai-response strong {
  color: #495057;
}
body {
  background-color: #eaf0fa;
}
</style>
