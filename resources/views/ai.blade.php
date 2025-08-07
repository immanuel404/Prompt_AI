<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prompt AI</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" rel="stylesheet">
    <style>
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
</head>

<body class="container py-5">
    <h1 class="mb-4">💬 Ask AI</h1><hr/><br/>

    <div id="loading-spinner" class="text-center mt-4 d-none">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Thinking...</p>
    </div>

    {{-- ENTER MESSAGE --}}
    <form id="ai-form" action="{{ route('ai.submit') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="model" class="form-label">Choose a Model</label>
            <select name="model" id="model" class="form-select">
                @foreach($models as $value => $label)
                    <option value="{{ $value }}" {{ old('model') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label for="dataset" class="form-label">Provide Dataset <span class="text-muted">(Optional)<span></label>
            <textarea name="dataset" id="dataset" rows="3" class="form-control">{{ old('dataset', $dataset ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label for="prompt" class="form-label">Enter Your Prompt</label>
            <textarea name="prompt" id="prompt" rows="2" class="form-control" required>{{ old('prompt', $prompt ?? '') }}</textarea>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" id="use_testdata_checkbox" type="checkbox" name="use_testdata_checkbox" value="1" {{ old('use_testdata_checkbox') ? 'checked' : '' }}>
            <label class="form-check-label" for="use_testdata_checkbox">Query Test Data</label>
        </div>
        <a href="/" class="btn btn-outline-dark btn-sm text-decoration-none px-2">Back</a>
        <button type="submit" class="btn btn-outline-success btn-sm">Submit</button>
    </form>

    {{-- AI RESPONSE --}}
    <div id="responses-container" class="mt-4">
    </div>
</body>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.0/marked.min.js"></script>
<script>
document.getElementById('ai-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const model = document.getElementById('model').value;
    const dataset = document.getElementById('dataset').value;
    const prompt = document.getElementById('prompt').value;
    const queryjsonCheckbox = document.getElementById('use_testdata_checkbox');
    const queryjson = queryjsonCheckbox.checked; // true or false
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const spinner = document.getElementById('loading-spinner');
    const sendBtn = this.querySelector('button[type="submit"]');

    spinner.classList.remove('d-none');
    sendBtn.disabled = true;

    fetch('/ai/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
            model: model,
            dataset: dataset,
            prompt: prompt,
            use_testdata_checkbox: queryjson ? 1 : 0
        })
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('responses-container');
        const div = document.createElement('div');
        div.className = 'ai-response';

        // Render Markdown to HTML and highlight code blocks
        let formattedMessage = marked.parse(data.message);
        formattedMessage = formattedMessage.replace(/<pre><code class="language-([^"]+)">/g, (match, lang) => {
            return `<pre><code class="language-${lang}">`;
        });

        div.innerHTML = `
            <strong>Prompt:</strong> ${data.prompt}
            <hr/>
            <strong>Response</strong> <span class='text-success'>[${data.model}]</span><strong>:</strong><br/>
            ${formattedMessage}
        `;
        container.prepend(div);

        // Highlight all code blocks in the newly added div
        div.querySelectorAll('pre code').forEach((el) => {
            hljs.highlightElement(el);
        });

        // Clear the form fields after a successful submission
        document.getElementById('dataset').value = '';
        document.getElementById('prompt').value = '';

        if(data.error) {
            console.error('Error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        spinner.classList.add('d-none');
        sendBtn.disabled = false;
    });
});
</script>

</html>
