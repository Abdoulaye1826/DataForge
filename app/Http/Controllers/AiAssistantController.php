<?php

namespace App\Http\Controllers;

use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\Project;
use App\Services\Ai\AiAssistantService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiAssistantService $assistant)
    {
    }

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $conversations = $this->assistant->conversationsForProject($project);
        $activeId = $request->integer('conversation') ?: $conversations->first()?->id;
        $active = $conversations->firstWhere('id', $activeId);

        return view('assistant.chat', [
            'project' => $project,
            'conversations' => $conversations,
            'active' => $active?->load('messages'),
        ]);
    }

    public function storeConversation(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $conversation = $this->assistant->startConversation(
            $project,
            $request->user(),
            $request->input('title') ?: 'Nouvelle conversation',
        );

        return redirect()->route('projects.assistant.index', [$project, 'conversation' => $conversation->id]);
    }

    public function storeMessage(Request $request, Project $project, AiConversation $aiConversation): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate(['content' => ['required', 'string', 'max:4000']]);

        try {
            $this->assistant->sendMessage($aiConversation, $project, $request->input('content'));
        } catch (AiProviderException $e) {
            return redirect()
                ->route('projects.assistant.index', [$project, 'conversation' => $aiConversation->id])
                ->withErrors(['assistant' => $e->getMessage()]);
        }

        return redirect()->route('projects.assistant.index', [$project, 'conversation' => $aiConversation->id]);
    }
}
