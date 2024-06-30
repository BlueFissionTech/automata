<?php
namespace BlueFission\Automata\LLM;

// https://bootcamp.uxdesign.cc/a-comprehensive-and-hands-on-guide-to-autonomous-agents-with-gpt-b58d54724d50
class Agent {
    private $tools = [];
    private $llm;

    public function __construct($llm) {
        $this->llm = $llm;
    }

    public function registerTool($name, $tool) {
        $this->tools[$name] = $tool;
    }

    public function execute($input) {
        $template = "Answer the following questions as best you can. You have access to the following tools: 
                     Calculator: Useful for when you need to answer questions about math.
                     Stock DB: Useful for when you need to answer questions about stocks and their prices.
                     Use the following format: 
                     Question: the input question you must answer
                     Thought: you should always think about what to do
                     Action: the action to take, should be one of [Calculator, Stock DB]
                     Action Input: the input to the action
                     Observation: the result of the action
                     Thought: I now know the final answer
                     Final Answer: the final answer to the original input question";

        // Create a prompt using the template
        $prompt = str_replace("{input}", $input, $template);

        // Query the LLM
        $response = $this->llm->complete($prompt);

        // Parse the response to get the actions and their inputs
        $actions = $response->extractActions();
        $inputs = $response->extractActionInputs();

        // Loop through each action-input pair and execute the corresponding tool
        $observations = [];
        for($i = 0; $i < count($actions); $i++) {
            $action = $actions[$i];
            $actionInput = $inputs[$i];

            // Execute the tool corresponding to the action
            $tool = $this->tools[$action];
            $observation = $tool->execute($actionInput);

            $observations[] = $observation;
        }

        // Return the final observation
        return $observations[count($observations) - 1];
    }
}