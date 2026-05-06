<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentComplianceValidationService
{
    private AcademicSectionClassifierService $sectionClassifier;

    /**
     * Matriz única de severidade (missing vs too_short):
     * - missing/empty em secção obrigatória => critical (bloqueia fluxo)
     * - too_short em metodologia => major (permite fluxo com reforço automático)
     */
    private const REQUIRED_SECTION_MISSING_SEVERITY = 'critical';

    public function __construct(?AcademicSectionClassifierService $sectionClassifier = null)
    {
        $this->sectionClassifier = $sectionClassifier ?? new AcademicSectionClassifierService();
    }

    public function validate(array $sections, array $blueprint, array $rules): array
    {
        $non = [];
        $normalizedTitles = array_map(fn($s) => $this->sectionClassifier->normalize((string)($s['title'] ?? '')), $sections);
        $needsMethod = (bool) ($rules['structureRules']['requires_methodology'] ?? false);
        $required = ['introducao', 'conclusao'];
        if ($needsMethod) { $required[] = 'metodologia'; }
        if (!empty($rules['referenceRules']['style'])) { $required[] = 'referencias'; }

        foreach ($required as $req) {
            if (!$this->hasEquivalent($req, $normalizedTitles)) {
                $non[] = ['severity'=>self::REQUIRED_SECTION_MISSING_SEVERITY,'rule'=>'required_section_missing','message'=>'Secção obrigatória ausente: '.$req,'target'=>$req];
            }
        }

        return $this->dedup(['is_compliant' => count(array_filter($non, fn($i)=>$i['severity']==='critical'))===0, 'summary' => $this->summary($non), 'non_conformities' => $non]);
    }

    private function dedup(array $v): array { $seen=[];$out=[];foreach($v['non_conformities'] as $n){$k=($n['severity']??'').($n['rule']??'').$this->sectionClassifier->normalize((string)($n['target']??'')).$this->sectionClassifier->normalize((string)($n['message']??''));if(isset($seen[$k]))continue;$seen[$k]=1;$out[]=$n;} $v['non_conformities']=$out;$v['summary']=$this->summary($out);$v['is_compliant']=(($v['summary']['critical']??0)===0);return $v; }
    private function summary(array $non): array { $s=['critical'=>0,'major'=>0,'minor'=>0,'warning'=>0]; foreach($non as $n){$sev=$n['severity']??'minor'; if(isset($s[$sev]))$s[$sev]++;} return $s; }
    private function hasEquivalent(string $needle, array $titles): bool { foreach($titles as $t){ if($this->sectionClassifier->classify(['title'=>$t]) === $needle) return true; if($this->sectionClassifier->areEquivalent($needle, $t)) return true;} return false; }

}
